"""Exercise the Media picker through the real Drupal SDK MCP transport."""

import json
import select
import subprocess
import os
from pathlib import Path
import tempfile


root = Path(os.environ.get('DRUPAL_ROOT', os.getcwd()))
site_url = os.environ.get('DRUPAL_BASE_URL') or os.environ.get('DDEV_PRIMARY_URL')
command = [str(root / 'vendor/bin/drush'), 'mcp:server', os.environ.get('DRUPAL_USER', 'admin')]
if site_url:
    command.append('--uri=' + site_url)

process = subprocess.Popen(
    command,
    cwd=root,
    stdin=subprocess.PIPE,
    stdout=subprocess.PIPE,
    stderr=subprocess.PIPE,
    text=True,
    bufsize=1,
)
sequence = 0


def request(method, params):
    global sequence
    sequence += 1
    process.stdin.write(json.dumps({"jsonrpc": "2.0", "id": sequence, "method": method, "params": params}) + "\n")
    process.stdin.flush()
    while True:
        ready, _, _ = select.select([process.stdout], [], [], 30)
        if not ready:
            raise RuntimeError("MCP server response timed out")
        line = process.stdout.readline()
        if not line:
            raise RuntimeError("MCP server stopped: " + process.stderr.read()[-3000:])
        reply = json.loads(line)
        if reply.get("id") != sequence:
            continue
        if "error" in reply:
            raise RuntimeError(str(reply["error"]))
        return reply["result"]


try:
    initialization = request("initialize", {
        "protocolVersion": "2025-03-26",
        "capabilities": {"extensions": {"io.modelcontextprotocol/ui": {"mimeTypes": ["text/html;profile=mcp-app"]}}},
        "clientInfo": {"name": "drupal-apps-protocol-smoke", "version": "1.0"},
    })
    process.stdin.write(json.dumps({"jsonrpc": "2.0", "method": "notifications/initialized"}) + "\n")
    process.stdin.flush()
    catalogue = []
    params = {}
    while True:
        page = request("tools/list", params)
        catalogue.extend(page["tools"])
        if not page.get("nextCursor"):
            break
        params = {"cursor": page["nextCursor"]}
    tools = {tool["name"]: tool for tool in catalogue}
    assert "content_atlas_open" not in tools
    assert "content_atlas_draft" not in tools
    assert "show_test_app" not in tools
    assert "canvas_composer_open" not in tools
    assert tools['tool_api__media_picker_save_hero']['_meta']['ui']['visibility'] == ['app']
    assert tools['tool_api__media_picker_save_hero']['annotations']['readOnlyHint'] is False
    for name, demo in [("tool_api__media_picker_open", "media-picker")]:
        uri = "ui://drupal/" + demo
        assert tools[name]["_meta"]["ui"]["resourceUri"] == uri
        assert tools[name]["annotations"]["readOnlyHint"] is True
        result = request("tools/call", {"name": name, "arguments": {}})
        assert not result.get("isError"), result
        data = result["structuredContent"]
        assert data["app"] == demo
        if site_url:
            assert data["origin"] == site_url.rstrip("/")
        assert len(result["content"]) == 2, "Text-only hosts need the data too."
        if demo == "media-picker":
            assert len(data["media"]) >= 6
            assert all(item["thumbnail"].startswith(data["origin"]) for item in data["media"])
            thumbnails = result["_meta"]["drupal/media-picker"]["thumbnails"]
            assert len(thumbnails) == len(data["media"])
            assert all(value.startswith("data:image/") for value in thumbnails.values())
            assert "thumbnails" not in data, "Image bytes are presentation metadata, not model data."
        content = request("resources/read", {"uri": uri})["contents"][0]
        assert content["mimeType"] == "text/html;profile=mcp-app"
        assert 'id="app-script"' in content["text"]
        assert content["_meta"]["ui"]["csp"]["resourceDomains"] == [data["origin"]]
        if demo == "media-picker":
            assert "__MEDIA_THUMBNAILS__" in content["text"]
            assert "base64," in content["text"], "Resource carries thumbnails if host omits tool result metadata."
        with open(Path(tempfile.gettempdir()) / (demo + "-fixture.json"), "w", encoding="utf-8") as fixture:
            json.dump(result, fixture)
        print(f"PASS: {name}, structured data, textual fallback, HTML/MIME/CSP and real SDK dispatch.")
    search = request("tools/call", {"name": "tool_api__media_picker_open", "arguments": {"query": "Rotterdam"}})
    assert len(search["structuredContent"]["media"]) == 4
    article = request('tools/call', {'name': 'tool_api__media_picker_open', 'arguments': {'article_title': 'weekend of discovery', 'query': 'Conference'}})
    assert article['structuredContent']['node_id'] > 0
    assert article['structuredContent']['hero_media'], 'Current hero survives a filtered library search.'
    invalid = request("tools/call", {"name": "tool_api__media_picker_open", "arguments": {"node_id": -1}})
    assert invalid["isError"] is True
    resources = request("resources/list", {})
    assert [item['uri'] for item in resources['resources'] if item['uri'].startswith('ui://drupal/')] == ['ui://drupal/media-picker']
    assert {name for name, tool in tools.items() if tool.get('_meta', {}).get('ui', {}).get('resourceUri', '').startswith('ui://drupal/')} == {'tool_api__media_picker_open', 'tool_api__media_picker_save_hero'}
    print("PASS: media search, article lookup, invalid input and the media-only app catalogue.")
    assert initialization["capabilities"]["extensions"]["io.modelcontextprotocol/ui"]["mimeTypes"] == ["text/html;profile=mcp-app"]
    assert "ui" in next(item for item in resources["resources"] if item["uri"] == "ui://drupal/media-picker")["_meta"]
    print("PASS: native Tool API bridge metadata and MCP Apps extension advertisement.")
finally:
    process.terminate()
    try:
        process.wait(timeout=5)
    except subprocess.TimeoutExpired:
        process.kill()
