const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../admin/settingTeacherManualComment.php'), 'utf8');
const scriptStart = source.lastIndexOf('<script>') + '<script>'.length;
// A Teacher-only login must have the same result-type choices as an administrator.
const script = source.slice(scriptStart, source.indexOf('</script>', scriptStart))
    .replace(/<\?php[\s\S]*?\?>/g, 'false');
const typeSelect = source.match(/<select[^>]*id="resultType"[^>]*>([\s\S]*?)<\/select>/)[1];
const types = [...typeSelect.matchAll(/<option value="([^"]+)"/g)].map(match => match[1]);
assert.deepEqual(types, ['termly', 'midterm', 'cummulative']);

const elements = new Map();
const handlers = [];
const requests = [];
const document = {};
function element(selector) {
    if (!elements.has(selector)) {
        elements.set(selector, {
            value: ['#session', '#term', '#staffid', '#class', '#classsection'].includes(selector) ? '0' : '',
            properties: {},
            content: '',
            val(value) { if (value === undefined) return this.value; this.value = value; return this; },
            prop(name, value) { this.properties[name] = value; return this; },
            html(value) { this.content = value; return this; },
            data(name) { return name === 'id' ? '2' : undefined; },
            on(event, target, handler) { handlers.push({ event, target, handler }); return this; },
            click() { return this; },
        });
    }
    return elements.get(selector);
}
function $(selector) {
    return typeof selector === 'object' && selector !== document ? selector : element(selector);
}
$.ajax = request => requests.push(request);
element('#resultType').val('termly');
vm.runInNewContext(script, { $, document });
function trigger(event, target, context) {
    const handler = handlers.find(item => item.event === event && item.target === target);
    assert.ok(handler, `Missing ${event} handler for ${target}`);
    handler.handler.call(context);
}
function change(target, value) {
    element(target).val(value);
    trigger('change', target);
}

assert.equal(element('#resultType').properties.disabled, true);
change('#session', '12');
assert.equal(element('#resultType').properties.disabled, true, 'A term is required.');
change('#term', '1st');
assert.equal(element('#resultType').properties.disabled, false, 'Teachers must be able to change the type.');
change('#staffid', '11');
change('#class', '4');
change('#classsection', '8');

for (const type of types) {
    change('#resultType', type);
    assert.match(element('#tbl_data').content, /Please Filter/, 'Changing type must clear the previous student list.');
    trigger('click', '#getstud');
    const load = requests.at(-1);
    assert.match(load.url, /view-studentmanualcomment\.php$/);
    assert.equal(load.data.resultType, type);
    assert.equal(load.data.RemarkType, 'teacher');
    assert.equal(load.data.staffid, '11');
    load.success('Student comments');
    assert.equal(element('#tbl_data').content, 'Student comments');

    const textarea = { data: () => '101', val: () => `${type} comment` };
    trigger('input', '.britishfield', textarea);
    const save = requests.at(-1);
    assert.match(save.url, /updateStudentmancomment\.php$/);
    assert.equal(save.data.resultType, type);
    assert.equal(save.data.term, type === 'cummulative' ? '3rd' : '1st');
    assert.equal(save.data.extcomment, `${type} comment`);
}

assert.equal(element('#term').properties.disabled, true, 'Cumulative uses the report card third-term context.');
assert.equal(element('#cumulativeCommentHint').properties.hidden, false);
change('#resultType', 'midterm');
assert.equal(element('#term').properties.disabled, false, 'Returning to Mid-term must unlock term selection.');
change('#term', '2nd');
assert.equal(element('#resultType').val(), 'midterm');
change('#session', '0');
assert.equal(element('#resultType').properties.disabled, true);
assert.equal(element('#resultType').val(), 'termly');
assert.equal(element('#cumulativeCommentHint').properties.hidden, true);

console.log('Teacher manual comment form tests passed (Teacher-only role, all types, load and save).');
