/* GumPress - MIT License */

const vscode = require('vscode');
const path	 = require('path');
const fs		 = require('fs');
const os		 = require('os');

const NAME_FILE_PATH = path.join(os.tmpdir(), 'gumpress-wp-execute.name');
const DONE_FILE_PATH = path.join(os.tmpdir(), 'gumpress-wp-execute.done');

let isExecuting			  = false;
let pollingInterval		  = null;

function activate(context)
{
	const CONFIG = {
		allowedFolders: ["tryout_code"],	// Empty array [] to allow all folders
		pathRegex: ""							// Optional regex to filter files
	};

	function isValidFile(editor) {
		if (!editor) return false;
		const filePath = editor.document.uri.fsPath;
		const workspaceFolder = vscode.workspace.getWorkspaceFolder(editor.document.uri);
		if (!workspaceFolder) return false;
		const relativePath = path.relative(workspaceFolder.uri.fsPath, filePath);
		const isPhp = editor.document.languageId === "php";
		let folderMatch = CONFIG.allowedFolders.length === 0 ||
			CONFIG.allowedFolders.some(folder => relativePath.startsWith(folder));
		let regexMatch = true;
		if (CONFIG.pathRegex && CONFIG.pathRegex.trim() !== "") {
			try {
				const regex = new RegExp(CONFIG.pathRegex);
				regexMatch = regex.test(relativePath);
			} catch (e) {
				regexMatch = false;
			}
		}
		return isPhp && folderMatch && regexMatch;
	}

	function updateVisibility() {
		if (isExecuting) {
			// Already executing; forcing button to stay hidden
			vscode.commands.executeCommand('setContext', 'gumpressButtonExecute.isValid', false);
			return;
		}
		const editor = vscode.window.activeTextEditor;
		const isValid = isValidFile(editor);
		vscode.commands.executeCommand('setContext', 'gumpressButtonExecute.isValid', isValid);
	}

	context.subscriptions.push(
		vscode.window.onDidChangeActiveTextEditor(updateVisibility),
		vscode.workspace.onDidChangeConfiguration(updateVisibility)
	);

	updateVisibility();

	const disposable = vscode.commands.registerCommand('gumpressButtonExecute.run', async () => {

		isExecuting = true;
		// Hides the button immediately
		updateVisibility(); 

		vscode.commands.executeCommand('setContext', 'gumpressButtonExecute.isValid', false);

		const editor = vscode.window.activeTextEditor;
		if (!isValidFile(editor)) {
			vscode.window.showWarningMessage("File non consentito dalla configurazione interna.");
			return;
		}

		const filePath		= editor.document.uri.fsPath;
		const encodedPath = Buffer.from(filePath).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

		try { fs.unlinkSync(NAME_FILE_PATH); } catch (e) { }
		try { fs.unlinkSync(DONE_FILE_PATH); } catch (e) { }

		fs.writeFileSync(NAME_FILE_PATH, encodedPath, 'utf8');

		const shCommand = "starting.sh";
		const terminalName = "bash";
		let terminal = vscode.window.terminals.find(t => t.name === terminalName);

		if (!terminal) {
			terminal = vscode.window.createTerminal({
				name: terminalName
			});
		}

		function startPolling(doneFilePath) {
			if (pollingInterval) return;

			let startTime = Date.now();
			const TIMEOUT_MS = 340000; // 333s curl + margine

			pollingInterval = setInterval(() => {
				// Timeout di emergenza
				if (Date.now() - startTime > TIMEOUT_MS) {
					stopAndReset();
					return;
				}

				if (fs.existsSync(doneFilePath)) {
					try { fs.unlinkSync(doneFilePath); } catch (e) { }
					stopAndReset();
				}
			}, 500);

			function stopAndReset() {
				clearInterval(pollingInterval);
				pollingInterval = null;
				isExecuting = false;
				updateVisibility();
			}
		}

		terminal.show();
		terminal.sendText(shCommand);
		startPolling(DONE_FILE_PATH);

	});

	context.subscriptions.push(disposable);
}

function deactivate() { }

module.exports = { activate, deactivate };