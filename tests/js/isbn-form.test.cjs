const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

const script = readFileSync('resources/views/books/create.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function form(response) {
    const elements = {};
    let click;
    let calls = 0;
    for (const id of ['fetch-btn', 'isbn-search', 'fetch-error', 'fetch-success', 'fetch-btn-label',
        'title', 'author', 'isbn', 'description', 'image_url', 'published_date']) {
        const classes = new Set(['hidden']);
        elements[id] = {
            value: '手入力', disabled: false,
            classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x) },
            addEventListener: (_, fn) => { click = fn; },
        };
    }
    elements['isbn-search'].value = '9781234567890';
    elements.published_date.value = '2020-01-01';
    vm.runInNewContext(script, {
        document: { getElementById: id => elements[id] },
        fetch: async () => { calls++; return response; },
    });
    return { elements, click: () => click.call(elements['fetch-btn']), calls: () => calls };
}

test('fills available values, keeps missing values and the requested ISBN', async () => {
    const f = form({ ok: true, json: async () => ({
        title: '取得タイトル', author: null, published_date: null, description: '', image_url: null,
    }) });
    await f.click();
    assert.equal(f.elements.title.value, '取得タイトル');
    assert.equal(f.elements.author.value, '手入力');
    assert.equal(f.elements.published_date.value, '2020-01-01');
    assert.equal(f.elements.description.value, '手入力');
    assert.equal(f.elements.isbn.value, '9781234567890');
    assert.equal(f.elements['fetch-btn'].disabled, false);
    assert.equal(f.elements['fetch-success'].classList.contains('hidden'), false);
});

test('rejects non-digit ISBN before making a request', async () => {
    const f = form({});
    f.elements['isbn-search'].value = 'abcdefghijklm';
    await f.click();
    assert.equal(f.calls(), 0);
    assert.equal(f.elements['fetch-error'].classList.contains('hidden'), false);
});

test('failed lookup preserves fields and shows server error', async () => {
    const f = form({ ok: false, json: async () => ({ error: '書籍が見つかりませんでした。' }) });
    await f.click();
    assert.equal(f.elements.title.value, '手入力');
    assert.equal(f.elements['fetch-error'].textContent, '書籍が見つかりませんでした。');
    assert.equal(f.elements['fetch-btn'].disabled, false);
});
