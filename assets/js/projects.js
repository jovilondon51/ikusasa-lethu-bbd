let projectEditor = null;
let selectedFile = null;
let loadSequence = 0;
require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
async function responseJson(response) {
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('The server could not complete this request. Please log in again or retry.'); }
    if (!response.ok || !result || result.ok !== true) throw new Error(result.message || 'The request failed.');
    return result;
}
document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('editorStatus');
    const save = document.getElementById('saveFileButton');
    document.querySelectorAll('.edit-button').forEach(button => button.addEventListener('click', async () => {
        const request = ++loadSequence;
        selectedFile = null; save.disabled = true;
        if (projectEditor) { projectEditor.dispose(); projectEditor = null; }
        document.getElementById('editorTitle').textContent = 'Edit ' + button.dataset.filePath.split('/').pop();
        status.textContent = 'Loading file…'; openModal('editorModal');
        try {
            const response = await fetch('/file.php?path=' + encodeURIComponent(button.dataset.filePath), { headers: { Accept: 'application/json' } });
            const result = await responseJson(response);
            if (request !== loadSequence) return;
            require(['vs/editor/editor.main'], () => {
                if (request !== loadSequence) return;
                const ext = button.dataset.filePath.split('.').pop().toLowerCase();
                projectEditor = monaco.editor.create(document.getElementById('editor'), {
                    value: result.content, language: { html: 'html', htm: 'html', css: 'css', js: 'javascript', json: 'json' }[ext] || 'plaintext',
                    theme: document.body.dataset.theme === 'dark' ? 'vs-dark' : 'vs', automaticLayout: true,
                    minimap: { enabled: false }, fontSize: 14, scrollBeyondLastLine: false
                });
                selectedFile = { projectId: button.dataset.projectId, path: button.dataset.filePath };
                save.disabled = false; status.textContent = '';
            });
        } catch (error) { if (request === loadSequence) status.textContent = error.message; }
    }));
    save.addEventListener('click', async () => {
        if (!projectEditor || !selectedFile) return;
        save.disabled = true; status.textContent = 'Saving…';
        try {
            const response = await fetch('/learner/projects.php', {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': projectCsrf },
                body: new URLSearchParams({ action: 'save_file', project_id: selectedFile.projectId, file_path: selectedFile.path, file_content: projectEditor.getValue() })
            });
            const result = await responseJson(response);
            status.textContent = result.message;
        } catch (error) { status.textContent = error.message; }
        finally { save.disabled = false; }
    });
    document.querySelectorAll('.preview-button').forEach(button => button.addEventListener('click', async () => {
        const previewStatus = document.getElementById('previewStatus');
        const frame = document.getElementById('previewFrame');
        frame.srcdoc = ''; previewStatus.textContent = 'Loading preview…'; openModal('previewModal');
        try {
            const response = await fetch('/preview.php?' + new URLSearchParams({ project_id: button.dataset.projectId, path: button.dataset.filePath }), { headers: { Accept: 'application/json' } });
            const result = await responseJson(response);
            frame.srcdoc = result.html; previewStatus.textContent = '';
        } catch (error) { previewStatus.textContent = error.message; }
    }));
    document.addEventListener('themechange', event => {
        if (projectEditor) monaco.editor.setTheme(event.detail === 'dark' ? 'vs-dark' : 'vs');
    });
});
