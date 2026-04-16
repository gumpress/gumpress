/* GumPress - MIT License */

const vscode = require('vscode');
const path	 = require('path');
const fs		 = require('fs');
const os		 = require('os');

const LOCK_FILE_PATH = path.join(os.tmpdir(), 'gumpress-wp-execute.lock');
const NAME_FILE_PATH = path.join(os.tmpdir(), 'gumpress-wp-execute.name');

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

		try { fs.unlinkSync(LOCK_FILE_PATH); } catch (e) {}
		try { fs.unlinkSync(NAME_FILE_PATH); } catch (e) {}

		fs.writeFileSync(NAME_FILE_PATH, encodedPath, 'utf8');

		const psCommand    = "starting.ps1";
		const terminalName = "Output WP Execute";
		let terminal = vscode.window.terminals.find(t => t.name === terminalName);
		
		if (!terminal) {
			terminal = vscode.window.createTerminal({
				name: terminalName,
				shellPath: "powershell.exe",
				shellArgs: ["-NoLogo", "-NoProfile", "-ExecutionPolicy", "Bypass"]
			});
		}

		function startPolling(lockFilePath) {
			if (pollingInterval) return;
	 
			let fileDetected = false;
			let startTime = Date.now();

			pollingInterval = setInterval(() => {
				// Wait for file creation (5s timeout if PS crashes immediately)
				if (!fileDetected) {
					if (fs.existsSync(lockFilePath)) {
						fileDetected = true;
					}
					else if (Date.now() - startTime > 5000) {
						// PowerShell didn't create the file in time
						stopAndReset();
					}
					return;
				}
				// File exists, try to open it to see if the lock has been released
				try {
					const fd = fs.openSync(lockFilePath, 'r+');
					fs.closeSync(fd);
					// If we reach this point, the lock has been removed
					stopAndReset();
					try { fs.unlinkSync(lockFilePath); } catch (e) {}
				}
				catch (err) {
					// EBUSY error indicates the script is still running; skipping
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
	//	terminal.sendText(""); 
	//	terminal.sendText("Clear-Host;");
		terminal.sendText(psCommand);
		startPolling(LOCK_FILE_PATH);

	});

	context.subscriptions.push(disposable);
}

function deactivate() { }

module.exports = { activate, deactivate };