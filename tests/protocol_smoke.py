"""Read-only MCP Apps regression through Drupal's real stdio SDK transport."""
import json
import os
import select
import subprocess
from pathlib import Path

root = Path(os.environ.get('DRUPAL_ROOT', os.getcwd()))
command = [str(root / 'vendor/bin/drush'), 'mcp:server', os.environ.get('DRUPAL_USER', 'admin')]
url = os.environ.get('DRUPAL_BASE_URL') or os.environ.get('DDEV_PRIMARY_URL')
if url:
    command.append('--uri=' + url)
process = subprocess.Popen(command, cwd=root, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, bufsize=1)
sequence = 0
theme = os.environ.get('MCP_APPS_PREVIEW_THEME', '')

def request(method, params):
    global sequence
    sequence += 1
    process.stdin.write(json.dumps({'jsonrpc': '2.0', 'id': sequence, 'method': method, 'params': params}) + '\n')
    process.stdin.flush()
    while True:
        if not select.select([process.stdout], [], [], 30)[0]:
            raise RuntimeError('MCP response timed out')
        line = process.stdout.readline()
        if not line:
            raise RuntimeError('MCP server stopped: ' + process.stderr.read()[-2000:])
        reply = json.loads(line)
        if reply.get('id') == sequence:
            assert 'error' not in reply, reply
            return reply['result']

def call(name, arguments):
    result = request('tools/call', {'name': 'tool_api__' + name, 'arguments': arguments})
    assert not result.get('isError'), result
    return result

try:
    initialized = request('initialize', {'protocolVersion': '2025-06-18', 'capabilities': {'extensions': {'io.modelcontextprotocol/ui': {'mimeTypes': ['text/html;profile=mcp-app']}}}, 'clientInfo': {'name': 'OpenUI smoke host', 'version': '1'}})
    assert 'io.modelcontextprotocol/ui' in initialized['capabilities']['extensions']
    process.stdin.write(json.dumps({'jsonrpc': '2.0', 'method': 'notifications/initialized'}) + '\n')
    process.stdin.flush()
    tools = {}
    params = {}
    while True:
        page = request('tools/list', params)
        tools.update({tool['name']: tool for tool in page['tools']})
        if not page.get('nextCursor'):
            break
        params = {'cursor': page['nextCursor']}
    uri = 'ui://drupal/component-composer'
    assert tools['tool_api__component_composer_open']['_meta']['ui']['resourceUri'] == uri
    assert tools['tool_api__component_composer_preview']['_meta']['ui']['visibility'] == ['app']
    assert tools['tool_api__component_composer_open']['annotations']['readOnlyHint'] is True
    assert not any(name in tools for name in ['tool_api__media_picker_open', 'tool_api__media_picker_save_hero', 'canvas_composer_open', 'content_atlas_open'])
    assert not any(name in tools for name in ['tool_api__editorial_activity', 'tool_api__activity_dashboard_open', 'tool_api__composer_media_search'])
    shop_uri = 'ui://drupal/commerce-shop'
    assert tools['tool_api__commerce_shop_open']['_meta']['ui']['resourceUri'] == shop_uri
    assert tools['tool_api__commerce_shop_checkout']['_meta']['ui']['visibility'] == ['app']
    assert tools['tool_api__commerce_demo_checkout']['annotations']['readOnlyHint'] is False
    shop_resource = request('resources/read', {'uri': shop_uri})['contents'][0]
    assert shop_resource['mimeType'] == 'text/html;profile=mcp-app'
    assert shop_resource['_meta']['ui']['csp']['connectDomains'] == []
    assert 'id="app"' in shop_resource['text']
    resource = request('resources/read', {'uri': uri})['contents'][0]
    assert resource['mimeType'] == 'text/html;profile=mcp-app'
    assert resource['_meta']['ui']['csp']['connectDomains'] == []
    assert 'id="app"' in resource['text']
    opened = call('component_composer_open', {'theme': theme})
    ui = opened['_meta']['ui']
    assert ui['catalog']
    shopping = call('commerce_shop_open', {'category': 'grinders', 'budget': 150})['_meta']['ui']
    assert shopping['tree'][0]['props']['comparing'] is True
    assert all(node['props']['show_details'] for node in shopping['tree'][0]['slots']['products'])
    legacy = call('commerce_shop_filter', {'session_id': shopping['session_id'], 'category': 'grinders', 'budget': 150})
    assert legacy['_meta']['ui']['view'] == 'catalog'
    ui['tree'] = shopping['tree']
    assert 'catalog' not in opened['structuredContent']['data']
    catalog = call('sdc_component_catalog', {'provider': 'mcp_apps_commerce_demo'})
    assert catalog['structuredContent'], catalog
    preview = call('component_composer_preview', {'session_id': ui['session_id'], 'composition': json.dumps(ui['tree'])})
    assert preview['structuredContent']['data']['valid'] is True
    assert 'html' not in preview['structuredContent']['data']
    assert 'Studio hand grinder' in preview['_meta']['ui']['html']
    assets = preview['_meta']['ui']['assets']
    assert assets
    asset = call('component_composer_asset', {'session_id': ui['session_id'], 'asset_id': next(iter(assets)), 'offset': 0})
    assert asset['_meta']['ui']['base64']
    assert 'base64' not in asset['structuredContent']['data']
    invalid = request('tools/call', {'name': 'tool_api__component_composer_preview', 'arguments': {'session_id': ui['session_id'], 'composition': '[{"component":"missing:component"}]'}})
    assert invalid['isError']
    print(f'PASS ({theme}): actual MCP extension, tool metadata, app resource, OpenUI preview, bounded assets and rejected input.')
finally:
    process.terminate()
    try:
        process.wait(timeout=5)
    except subprocess.TimeoutExpired:
        process.kill()
