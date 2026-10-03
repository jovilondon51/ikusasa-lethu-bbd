const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const context = vm.createContext({ require: { config() {} }, document: { addEventListener() {} } });
vm.runInContext(fs.readFileSync(__dirname + '/../assets/js/projects.js', 'utf8'), context);
(async () => {
    assert.equal((await context.responseJson({ ok: true, json: async () => ({ ok: true, message: 'Saved' }) })).message, 'Saved');
    await assert.rejects(() => context.responseJson({ ok: false, json: async () => ({ ok: true }) }));
    await assert.rejects(() => context.responseJson({ ok: true, json: async () => ({ ok: false, message: 'Write failed' }) }), /Write failed/);
    await assert.rejects(() => context.responseJson({ ok: false, json: async () => { throw new Error('Not JSON'); } }), /server could not complete/);
    console.log('4 save-response checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
