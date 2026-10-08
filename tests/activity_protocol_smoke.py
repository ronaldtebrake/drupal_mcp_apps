"""Read-only activity demo through Drupal's real MCP SDK transport."""
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

def request(method, params, allow_error=False):
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
            if allow_error and 'error' in reply:
                return reply
            assert 'error' not in reply, reply
            return reply['result']

def call(name, arguments):
    result = request('tools/call', {'name': 'tool_api__' + name, 'arguments': arguments})
    assert not result.get('isError'), result
    return result

try:
    initialized = request('initialize', {'protocolVersion': '2025-06-18', 'capabilities': {'extensions': {'io.modelcontextprotocol/ui': {'mimeTypes': ['text/html;profile=mcp-app']}}}, 'clientInfo': {'name': 'Activity smoke host', 'version': '1'}})
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
    uri = 'ui://drupal/editorial-activity'
    assert tools['tool_api__activity_dashboard_open']['_meta']['ui']['resourceUri'] == uri
    assert tools['tool_api__activity_dashboard_filter']['_meta']['ui']['visibility'] == ['app']
    assert '_meta' not in tools['tool_api__editorial_activity']
    assert tools['tool_api__activity_dashboard_open']['annotations']['readOnlyHint'] is True
    for name in ['editorial_activity', 'activity_dashboard_open', 'activity_dashboard_filter']:
        days_schema = tools['tool_api__' + name]['inputSchema']['properties']['days']
        assert days_schema['minimum'] == 1 and days_schema['maximum'] == 30
        assert 'enum' not in days_schema
    resource = request('resources/read', {'uri': uri})['contents'][0]
    assert resource['mimeType'] == 'text/html;profile=mcp-app'
    assert resource['_meta']['ui']['csp']['connectDomains'] == []
    opened = call('activity_dashboard_open', {})
    ui = opened['_meta']['ui']
    assert ui['tree'][0]['component'] == 'mcp_apps_activity_demo:dashboard'
    assert 'tree' not in opened['structuredContent']['data']
    preview = call('component_composer_preview', {'session_id': ui['session_id'], 'composition': json.dumps(ui['tree'])})
    assert preview['structuredContent']['data']['valid'] is True
    assert 'data-pulse-chart' in preview['_meta']['ui']['html']
    asset_id = next(iter(preview['_meta']['ui']['assets']))
    assert call('component_composer_asset', {'session_id': ui['session_id'], 'asset_id': asset_id, 'offset': 0})['_meta']['ui']['base64']
    report = call('editorial_activity', {'days': 7, 'section': 'culture'})['structuredContent']['data']
    assert report['demo'] is True and len(report['series']) == 7
    assert all(article['section'] == 'culture' for article in report['articles'])
    filtered = call('activity_dashboard_filter', {'session_id': ui['session_id'], 'days': 7, 'section': 'culture'})
    assert filtered['structuredContent']['data']['reads'] == report['reads']
    for view, slot in [('readership', 'chart'), ('stories', 'content'), ('activity', 'activity'), ('metrics', 'metrics')]:
        focused = call('activity_dashboard_open', {'view': view, 'days': 30})['_meta']['ui']
        assert focused['tree'] == ui['tree'][0]['slots'][slot]
        changed = call('activity_dashboard_filter', {'session_id': focused['session_id'], 'days': 7, 'section': 'culture'})
        assert changed['_meta']['ui']['view'] == view
        assert changed['structuredContent']['data']['reads'] == report['reads']
        standalone = call('component_composer_preview', {'session_id': focused['session_id'], 'composition': json.dumps(changed['_meta']['ui']['tree'])})
        assert 'class="pulse-dashboard"' not in standalone['_meta']['ui']['html']
    for days in [1, 13, 14, 30]:
        report = call('editorial_activity', {'days': days})['structuredContent']['data']
        assert len(report['series']) == days and len(report['previous']) == days
    custom = call('activity_dashboard_open', {'view': 'readership', 'days': 14})['_meta']['ui']
    assert len(custom['tree'][0]['props']['current']) == 14
    custom = call('activity_dashboard_filter', {'session_id': custom['session_id'], 'days': 13, 'section': 'culture'})['_meta']['ui']
    assert custom['view'] == 'readership' and len(custom['tree'][0]['props']['current']) == 13
    for days in [0, 31, 14.5, 365]:
        invalid = request('tools/call', {'name': 'tool_api__editorial_activity', 'arguments': {'days': days}}, allow_error=True)
        assert invalid['error']['code'] == -32602
    print('PASS: MCP Apps metadata/resource, reusable data tool, filtered native SDC composition, attached assets and input rejection.')
finally:
    process.terminate()
    try:
        process.wait(timeout=5)
    except subprocess.TimeoutExpired:
        process.kill()
