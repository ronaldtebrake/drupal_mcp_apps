import { $, element, host, imageUrl, showError } from './common.js';

const state = { data: null, thumbnails: {}, heroThumbnails: {}, library: new Map(), post: null, proposed: false, query: '', selected: null, alt: '', sent: false, busy: false };
const bridge = host('media-picker', 'Drupal Media Picker', receive);

function receive(result) {
  if (result.isError) { showError(new Error(result.content?.[0]?.text || 'Media unavailable.')); return; }
  const data = result.structuredContent;
  if (data?.app === 'media-picker-hero-saved') {
    state.post = data.post;
    state.data.posts = state.data.posts.map((post) => post.id === data.post.id ? data.post : post);
    state.selected = null; state.proposed = false;
    render(); $('selection-feedback').textContent = data.message;
    bridge.context({ article: { id: data.post.id, title: data.post.title }, saved_hero: data.post.hero });
    return;
  }
  if (data?.app !== 'media-picker' || !Array.isArray(data.media)) return;
  state.data = data;
  state.thumbnails = { ...globalThis.__MEDIA_THUMBNAILS__, ...state.thumbnails, ...result._meta?.['drupal/media-picker']?.thumbnails };
  state.heroThumbnails = { ...globalThis.__MEDIA_HERO_THUMBNAILS__, ...state.heroThumbnails, ...result._meta?.['drupal/media-picker']?.heroThumbnails };
  [...data.media, ...(data.hero_media || [])].forEach((item) => state.library.set(item.id, item));
  const target = data.node_id || state.post?.id;
  state.post = target ? data.posts?.find((post) => post.id === target) || null : data.posts?.[0] || null;
  state.query = '';
  $('search').value = '';
  if (state.selected) {
    const current = data.media.find((item) => item.id === state.selected.id);
    state.selected = current || null;
    if (!current) state.alt = '';
  }
  document.body.dataset.ready = 'true';
  $('error').hidden = true;
  render();
}

function thumbnailUrl(item, hero = false) {
  const inline = (hero ? state.heroThumbnails : state.thumbnails)[item.id];
  if (typeof inline === 'string' && inline.length < 750000 && /^data:image\/(jpeg|png|webp|gif);base64,[A-Za-z0-9+/=]+$/.test(inline)) return inline;
  return imageUrl(hero ? item.hero_thumbnail : item.thumbnail, state.data.origin);
}

function render() {
  const visible = state.data.media.filter((item) => `${item.name} ${item.alt}`.toLowerCase().includes(state.query.toLowerCase()));
  $('media-grid').replaceChildren();
  $('result-count').classList.remove('loading');
  $('result-count').textContent = `${visible.length} ${visible.length === 1 ? 'image' : 'images'}${state.data.query ? ` matching “${state.data.query}”` : ' in your library'}${state.data.limited ? ' · First 200 media scanned' : ''}`;
  for (const item of visible) {
    const card = element('button', 'media-card');
    card.type = 'button'; card.dataset.id = item.id;
    card.disabled = state.busy;
    card.setAttribute('aria-pressed', String(state.selected?.id === item.id));
    card.setAttribute('aria-label', `Select ${item.name}`);
    const url = thumbnailUrl(item);
    if (url) { const image = element('img'); image.src = url; image.alt = item.alt || item.name; image.loading = 'lazy'; card.append(image); }
    else { card.append(element('div', 'empty', 'Image unavailable')); }
    card.append(element('span', 'check', '✓'));
    const copy = element('div', 'card-copy');
    copy.append(element('strong', '', item.name), element('span', 'muted', `${item.width} × ${item.height}`));
    const tags = element('div', 'card-tags');
    tags.append(element('span', 'tag', item.demo ? 'SAMPLE PHOTO' : 'DRUPAL MEDIA'));
    copy.append(tags); card.append(copy);
    card.addEventListener('click', () => {
      state.selected = item; state.alt = item.alt; state.sent = false;
      state.proposed = true; state.reviewing = false;
      render(); bridge.context({ selected_media: { id: item.id, name: item.name, proposed_alt: state.alt } });
      document.querySelector(`#media-grid [data-id="${item.id}"]`)?.focus();
    });
    $('media-grid').append(card);
  }
  if (!visible.length) $('media-grid').append(element('div', 'panel empty', 'No matching images. Try another search or search Drupal again.'));
  renderDetail();
  renderArticle();
}

function renderArticle() {
  const post = state.post;
  $('article-title').textContent = post?.title || 'Choose an article to begin';
  $('article-byline').textContent = post ? `By ${post.byline} · ${post.date}` : '';
  $('article-summary').textContent = post?.summary || '';
  $('show-current').setAttribute('aria-pressed', String(!state.proposed));
  $('show-proposed').setAttribute('aria-pressed', String(state.proposed));
  $('show-proposed').disabled = !state.selected;
  const item = state.proposed ? state.selected : state.library.get(post?.hero?.id);
  const box = $('article-image'); box.replaceChildren();
  const url = item && thumbnailUrl(item, true);
  if (url) { const image = element('img'); image.src = url; image.alt = state.proposed ? state.alt : post.alt; box.append(image); }
  else box.append(element('div', 'empty', post ? 'No hero image available' : 'Your article preview will appear here'));
}

function renderDetail() {
  const item = state.selected;
  $('selection-empty').hidden = Boolean(item);
  $('selection-detail').hidden = !item;
  $('selection-detail').replaceChildren();
  $('selection-id').textContent = item ? `Media #${item.id}` : '—';
  if (!item) { const feedback = element('p', 'status'); feedback.id = 'selection-feedback'; feedback.setAttribute('role', 'status'); $('selection-detail').append(feedback); return; }
  const box = $('selection-detail');
  const url = thumbnailUrl(item);
  if (url) { const image = element('img'); image.src = url; image.alt = state.alt; box.append(image); }
  box.append(element('h2', '', item.name), element('p', 'caption', `${item.width} × ${item.height} · ${item.filename}`));
  const label = element('label', 'field-label', 'Alt text for your article'); label.htmlFor = 'proposed-alt';
  const input = element('textarea', 'alt-input'); input.id = 'proposed-alt'; input.maxLength = 500; input.value = state.alt;
  input.disabled = state.busy;
  input.addEventListener('input', () => { state.alt = input.value; state.sent = false; $('use-media').textContent = state.post ? 'Save hero to article' : 'Use this media →'; $('selection-feedback').textContent = ''; renderArticle(); bridge.context({ selected_media: { id: item.id, name: item.name, proposed_alt: state.alt } }); });
  box.append(label, input, element('p', 'caption', 'Describe what matters in the image. This is a proposed value; the stored Media alt text stays unchanged.'));
  if (item.credit) box.append(element('p', 'credit', `${item.credit}. Sample stock photography; not actual DrupalCon coverage.`));
  const button = element('button', 'primary', state.post ? 'Save hero to article' : state.sent ? 'Selection sent ✓' : 'Use this media →'); button.id = 'use-media'; button.type = 'button';
  button.disabled = state.busy || !bridge.connected || (state.post ? !state.post.can_update || !state.alt.trim() : !bridge.app?.getHostCapabilities()?.message);
  button.addEventListener('click', async () => {
    if (state.post) { await saveHero(item); return; }
    state.busy = true; button.disabled = true; input.disabled = true;
    try {
      await bridge.app.sendMessage({ role: 'user', content: [{ type: 'text', text: `I selected this Drupal image for my article. No Drupal content has been changed.\n${JSON.stringify({ media_id: item.id, name: item.name, proposed_alt: state.alt })}` }] });
      state.sent = true; button.textContent = 'Selection sent ✓'; $('selection-feedback').textContent = 'Media ID and proposed alt text sent to the conversation.';
    } catch (error) { showError(error); }
    finally { state.busy = false; button.disabled = false; input.disabled = false; }
  });
  box.append(button, element('p', 'status', bridge.preview ? 'Chat handoff is available in the MCP App.' : ''));
  box.lastChild.id = 'selection-feedback'; box.lastChild.setAttribute('role', 'status');
}

async function saveHero(item) {
  if (state.busy || !state.post?.can_update || !bridge.connected || !state.alt.trim()) return;
  state.busy = true; renderDetail();
  document.querySelectorAll('.media-card').forEach((card) => { card.disabled = true; });
  try {
    const result = await bridge.app.callServerTool({ name: 'tool_api__media_picker_save_hero', arguments: { node_id: state.post.id, media_id: item.id, alt: state.alt.trim(), revision: state.post.revision } });
    if (result.isError) throw new Error(result.content?.[0]?.text || 'The hero could not be saved. Refresh and try again.');
    receive(result);
  } catch (error) { showError(error); }
  finally { state.busy = false; if (state.selected) render(); else { document.querySelectorAll('.media-card').forEach((card) => { card.disabled = false; }); $('selection-empty').append($('selection-feedback')); } }
}

$('show-current').addEventListener('click', () => { state.proposed = false; renderArticle(); });
$('show-proposed').addEventListener('click', () => { state.proposed = true; renderArticle(); });

$('search').addEventListener('input', (event) => { state.query = event.target.value; if (state.data) render(); });
$('search-form').addEventListener('submit', async (event) => {
  event.preventDefault(); if (state.busy) return;
  state.busy = true; $('search-drupal').disabled = true;
  try { await bridge.call({ query: $('search').value, node_id: state.post?.id || 0 }); } catch (error) { showError(error); }
  finally { state.busy = false; $('search-drupal').disabled = !bridge.connected; if (state.data) renderDetail(); }
});
document.addEventListener('host-connected', () => { $('search-drupal').disabled = false; if (state.data) renderDetail(); });
$('search-drupal').disabled = true;
bridge.start();
