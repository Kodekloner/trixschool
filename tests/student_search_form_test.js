const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Run the page's actual submission script. Model browser focus and submitter
// separately so mouse submission and Enter-key submission both get exercised.
const source = fs.readFileSync(path.join(__dirname, '../application/views/student/studentSearch.php'), 'utf8');
const scriptStart = source.lastIndexOf('<script type="text/javascript">');
const script = source.slice(scriptStart + '<script type="text/javascript">'.length, source.indexOf('</script>', scriptStart));
let submitHandler;
let keywordKeyHandler;
let activeButton;
let keywordFocused;
let keyword;
let ajaxOptions;
let tableOptions;
const document = {};
const buttons = Object.fromEntries(['search_full', 'search_filter'].map(value => [value, {
    length: 1,
    attr: () => value,
    button: () => {},
    trigger: event => {
        assert.equal(event, 'click');
        submitHandler.call(form, { preventDefault: () => {}, originalEvent: { submitter: buttons[value] } });
    },
}]));
const missingButton = { length: 0, attr: () => undefined };
const form = {
    attr: () => '/student/searchvalidation',
    find: selector => selector.includes(':focus') ? (activeButton || missingButton) : buttons[selector.match(/value="([^"]+)"/)[1]],
    serializeArray: () => [
        { name: 'class_id', value: '4' },
        { name: 'section_id', value: '2' },
        { name: 'search_text', value: keyword },
    ],
};
const keywordInput = {
    val: value => value === undefined ? keyword : (keyword = value),
    is: () => keywordFocused,
    closest: () => form,
};
const sectionInput = { find: () => ({ not: () => ({ remove: () => {} }) }) };
const tableElement = {
    data: () => 'Students',
    DataTable: options => { tableOptions = options; return {}; },
};
function $(target) {
    if (target === document) {
        return { ready: fn => fn(), on: (event, selector, handler) => {
            if (event === 'submit') { submitHandler = handler; }
            if (event === 'keydown') { keywordKeyHandler = handler; }
        } };
    }
    if (target === form || target === keywordInput || Object.values(buttons).includes(target)) { return target; }
    if (target === '#search_text') { return keywordInput; }
    if (target === '#section_id') { return sectionInput; }
    if (target === '#class_id') { return { prop: () => {} }; }
    if (target === '.student-list') { return tableElement; }
    return { html: () => {} };
}
$.trim = value => value.trim();
$.fn = { DataTable: { isDataTable: () => false } };
$.ajax = options => { ajaxOptions = options; options.beforeSend(); };
vm.runInNewContext(script, { $, document, baseurl: 'https://school.test/' });
assert.equal(typeof submitHandler, 'function');
assert.equal(typeof keywordKeyHandler, 'function');

keyword = 'Ada Okafor';
keywordFocused = true;
let enterPrevented = false;
keywordKeyHandler.call(keywordInput, { key: 'Enter', preventDefault: () => { enterPrevented = true; } });
assert.equal(enterPrevented, true, 'Enter must prevent the browser from choosing the first class-search button.');
assert.equal(ajaxOptions.data.find(field => field.name === 'search_type').value, 'search_full');

function submit({ text = '', focusedKeyword = false, focusedButton, submitter } = {}) {
    keyword = text;
    keywordFocused = focusedKeyword;
    activeButton = focusedButton;
    let prevented = false;
    submitHandler.call(form, { preventDefault: () => { prevented = true; }, originalEvent: { submitter } });
    assert.equal(prevented, true);
    return Object.fromEntries(ajaxOptions.data.map(field => [field.name, field.value]));
}

let payload = submit({ text: 'Ada Okafor', focusedKeyword: true });
assert.equal(payload.search_type, 'search_full', 'Enter in the keyword input must submit a keyword search.');
assert.equal(payload.search_text, 'Ada Okafor');
payload = submit({ text: 'Ada', focusedKeyword: true, submitter: buttons.search_filter });
assert.equal(payload.search_type, 'search_filter', 'The browser submitter must select the clicked button even while the keyword has focus.');
assert.equal(payload.class_id, '4');
assert.equal(keyword, '', 'Class search must clear the keyword after serializing the form.');
payload = submit({ text: 'Ada', submitter: buttons.search_full });
assert.equal(payload.search_type, 'search_full');
payload = submit({ text: 'Ada', focusedButton: buttons.search_filter });
assert.equal(payload.search_type, 'search_filter', 'Focused-button fallback must still support older browsers.');
payload = submit();
assert.equal(payload.search_type, 'search_filter', 'Enter in class controls with no keyword must submit a class search.');
payload = submit({ focusedKeyword: true });
assert.equal(payload.search_type, 'search_full', 'An empty keyword submitted with Enter must use the same mode as its search button.');

const validatedParams = { srch_type: 'search_full', search_text: 'Ada' };
ajaxOptions.success({ status: 1, params: validatedParams });
assert.equal(tableOptions.ajax.url, 'https://school.test/student/dtstudentlist');
assert.equal(tableOptions.ajax.data.search_text, 'Ada', 'Every table redraw must retain the validated keyword.');
assert.equal(tableOptions.serverSide, true);
assert.equal(tableOptions.columnDefs[0].targets, -1);
assert.equal(tableOptions.columnDefs[0].orderable, false, 'The action column must not send an unsupported sort request.');
assert.doesNotThrow(() => tableOptions.drawCallback({}), 'A failed request must not crash the detail-view callback.');
tableOptions.drawCallback({ json: { student_detail_view: 'matching students' } });

console.log('student search form tests passed');
