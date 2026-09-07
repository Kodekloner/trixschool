'use strict';
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../application/views/user/onlineexam/view_v2.php'), 'utf8');
const timer = source.slice(source.indexOf('    function startTimer(seconds) {'), source.indexOf("    $(document).on('click', '.v2-start-paper'"));
assert(timer.includes('function startTimer'), 'Extract the real paper countdown for regression testing.');
const elements = {};
let submissions = 0;
let scheduled = null;
let error = null;
const context = vm.createContext({
    timerHandle: null,
    $: selector => ({
        text(value) { elements[selector] = value; return this; },
        attr() { return this; },
        toggleClass() { return this; }
    }),
    clearInterval() { scheduled = null; },
    setInterval(callback) { scheduled = callback; return 1; },
    submitPaper(timedOut) { assert.strictEqual(timedOut, true); submissions++; },
    message(value) { error = value; }
});
// This exact library is loaded by the student portal header and changes Date.now().
vm.runInContext(fs.readFileSync(path.join(__dirname, '../backend/datepicker/date.js'), 'utf8'), context);
assert.strictEqual(vm.runInContext('typeof Date.now()', context), 'object');
vm.runInContext(timer, context);
vm.runInContext('startTimer(3661)', context);
assert.strictEqual(elements['#v2PaperTimer'], '01:01:01', 'DateJS must not turn the deadline into a concatenated string.');
assert.strictEqual(submissions, 0);
assert.strictEqual(typeof scheduled, 'function');
for (const bad of ['undefined', 'null', '""', '"bad"', 'Infinity', '1e308', '-1', 'true']) {
    vm.runInContext('startTimer(' + bad + ')', context);
    assert.strictEqual(elements['#v2PaperTimer'], '--:--:--');
    assert.strictEqual(submissions, 0, 'A malformed time must never auto-submit the student paper.');
    assert.strictEqual(scheduled, null);
    assert(error);
}
vm.runInContext('startTimer(0)', context);
assert.strictEqual(elements['#v2PaperTimer'], '00:00:00');
assert.strictEqual(submissions, 1, 'A valid expired server timer submits exactly once.');
assert.strictEqual(scheduled, null, 'Do not schedule a second expiry tick after immediate submission.');
console.log('Online Examination countdown regression tests passed with the portal DateJS library.');
