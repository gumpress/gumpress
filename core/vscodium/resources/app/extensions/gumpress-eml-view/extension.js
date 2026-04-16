/* GumPress - MIT License */

const vscode = require('vscode');
const fs = require('fs');

async function getPostalMime() {
   const { default: PostalMime } = await import('postal-mime');
   return PostalMime;
}

function activate(context) {
   context.subscriptions.push(
      vscode.window.registerCustomEditorProvider(
         'emlpreview.viewer',
         new EmlEditorProvider(context),
         { webviewOptions: { retainContextWhenHidden: true } }
      )
   );
}

class EmlEditorProvider {
   constructor(context) {
      this.context = context;
      this.output = vscode.window.createOutputChannel("EML Previewer");
   }

   async resolveCustomTextEditor(document, webviewPanel, _token) {
      webviewPanel.webview.options = {
         enableScripts: true,
         localResourceRoots: [vscode.Uri.joinPath(this.context.extensionUri, 'node_modules')]
      };

      const raw = document.getText();
        
      try {
         const PostalMime = await getPostalMime();
         const email = await new PostalMime().parse(raw);

         webviewPanel.webview.onDidReceiveMessage(async (message) => {
            if (message.command === 'downloadAttachment') {
               this.handleDownload(message.filename, message.content);
            }
         });

         webviewPanel.webview.html = getHtml(email, raw);
      } catch (err) {
         this.output.appendLine(`[Parsing Error] ${err.message}`);
         webviewPanel.webview.html = `
               <div style="font-family: var(--vscode-font-family); padding: 20px; color: var(--vscode-errorForeground);">
                  <h2 style="display: flex; align-items: center; gap: 10px;">
                     <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                     Unable to parse EML file
                  </h2>
                  <p>The file might be corrupted or not a valid EML format.</p>
                  <pre style="background: var(--vscode-textCodeBlock-background); padding: 10px; border-radius: 4px; overflow: auto;">${err.message}</pre>
               </div>`;
      }
   }

   async handleDownload(filename, base64Content) {
      const uri = await vscode.window.showSaveDialog({
         defaultUri: vscode.Uri.file(filename),
         title: 'Save Attachment'
      });

      if (uri) {
         const buffer = Buffer.from(base64Content, 'base64');
         fs.writeFileSync(uri.fsPath, buffer);
         vscode.window.showInformationMessage(`Attachment saved: ${filename}`);
      }
   }
}

function getHtml(email, raw) {
   const totalSize = (Buffer.byteLength(raw, 'utf8') / 1024).toFixed(1) + ' KB';

   const attachments = (email.attachments || []).map(att => ({
      filename: att.filename || 'attachment',
      mimeType: att.mimeType,
      size: (att.content.byteLength / 1024).toFixed(1) + ' KB',
      content: Buffer.from(att.content).toString('base64')
   }));

   const paperclipIcon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.51a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>`;

   const attachmentsHtml = attachments.map((att, index) => `
        <div class="attachment-card" onclick="download(${index})" title="Click to download ${att.filename}">
            <div class="att-icon">${paperclipIcon}</div>
            <div class="att-info">
                <span class="att-name">${att.filename}</span>
                <span class="att-size">${att.size}</span>
            </div>
        </div>`).join('');

   return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        :root { --font-family: var(--vscode-font-family, 'Segoe UI', sans-serif); }
        body { 
            font-family: var(--font-family); 
            color: var(--vscode-foreground); 
            background: var(--vscode-editor-background); 
            padding: 20px; 
            line-height: 1.4;
            margin: 0;
        }
        .header-section { border-bottom: 1px solid var(--vscode-panel-border); padding-bottom: 15px; margin-bottom: 20px; }
        .subject { font-size: 1.5em; font-weight: bold; margin-bottom: 10px; color: var(--vscode-editor-foreground); }
        .meta-row { font-size: 0.9em; margin-bottom: 4px; color: var(--vscode-descriptionForeground); }
        .meta-label { font-weight: bold; width: 60px; display: inline-block; color: var(--vscode-foreground); }

        /* Griglia Allegati Uniforme */
        .attachments-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 10px; 
            margin: 15px 0; 
        }
        .attachment-card { 
            display: flex; align-items: center; gap: 8px; padding: 8px 12px; 
            background: var(--vscode-widget-subtle-background, var(--vscode-button-secondaryBackground)); 
            color: var(--vscode-foreground);
            border-radius: 6px; cursor: pointer; border: 1px solid var(--vscode-panel-border);
            transition: all 0.2s ease;
            overflow: hidden;
        }
        .attachment-card:hover { border-color: var(--vscode-focusBorder); background: var(--vscode-toolbar-hoverBackground); }
        .att-icon { display: flex; align-items: center; flex-shrink: 0; color: var(--vscode-textLink-foreground); }
        .att-info { display: flex; flex-direction: column; line-height: 1.2; overflow: hidden; }
        .att-name { font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .att-size { font-size: 10px; opacity: 0.6; }

        .mail-body { margin: 20px 0; padding: 0; line-height: 0; }
        iframe { 
            width: 100%; border: 1px solid var(--vscode-panel-border); 
            background: white; border-radius: 8px; overflow: hidden; 
            display: block; margin: 0; padding: 0;
        }
        .text-body { 
            white-space: pre-wrap; padding: 15px; line-height: 1.4;
            background: var(--vscode-textCodeBlock-background); border-radius: 4px; 
        }

        /* Sorgente EML - Muted */
        details { margin-top: 20px; border-top: 1px solid var(--vscode-panel-border); padding-top: 10px; }
        summary { 
            cursor: pointer; color: var(--vscode-descriptionForeground); font-size: 0.85em; 
            transition: color 0.2s; outline: none; list-style: none;
        }
        summary::-webkit-details-marker { display: none; }
        summary:hover { color: var(--vscode-textLink-foreground); }
        .raw-content { 
            font-family: var(--vscode-editor-font-family, monospace); font-size: 11px; line-height: 1.4;
            background: var(--vscode-textCodeBlock-background); padding: 10px; margin-top: 10px; border-radius: 4px;
            overflow-x: auto;
        }
        .total-size-label { opacity: 0.6; margin-left: 5px; font-weight: normal; }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="subject">${email.subject || '(No Subject)'}</div>
        <div class="meta-row"><span class="meta-label">From:</span> ${email.from?.address || 'N/A'}</div>
        <div class="meta-row"><span class="meta-label">Date:</span> ${email.date || 'N/A'}</div>
    </div>

    ${attachmentsHtml ? `<div class="attachments-container">${attachmentsHtml}</div>` : ''}

    <div class="mail-body">
        ${email.html 
         ? `<iframe srcdoc="<html><body style='font-family:sans-serif; padding:15px; margin:0; color:#333; line-height:1.4;'>${email.html.replace(/"/g, '&quot;')}</body></html>" onload="this.style.height=(this.contentWindow.document.documentElement.scrollHeight)+'px';"></iframe>`
         : `<div class="text-body">${email.text || ''}</div>`
      }
    </div>

    <details>
        <summary>Original message source <span class="total-size-label">(${totalSize})</span></summary>
        <pre class="raw-content">${raw.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>
    </details>

    <script>
        const vscode = acquireVsCodeApi();
        const atts = ${JSON.stringify(attachments)};

        function download(index) {
            const att = atts[index];
            vscode.postMessage({
                command: 'downloadAttachment',
                filename: att.filename,
                content: att.content
            });
        }
    </script>
</body>
</html>`;
}

module.exports = { activate, deactivate: () => {  } };