chrome.tabs.onCreated.addListener((tab) => {
	if (tab.pendingUrl === "chrome://newtab/" || tab.url === "chrome://newtab/") {
	//	chrome.tabs.update(tab.id, { url: 'https://wordpress.test/!/newtab.html' });
		chrome.tabs.remove(tab.id);
		chrome.tabs.create({ url: 'https://wordpress.test/!/newtab.html', active: true });
		return true;
	}
});

chrome.runtime.onMessage.addListener((msg) => {
	if (msg.type !== 'MENU') return;
	processMenu(msg.url);
	return true;
});

chrome.runtime.onMessageExternal.addListener(
	// Use javascript:chrome.runtime.sendMessage('pijampllaceajkffjniikgfenacidlkn', '???') where '???' === request
	(request, sender, sendResponse) => {
		processMenu(request);
		return true;
	}
);

function processMenu(url)
{
	let callUrl = url.toLowerCase();
	chrome.tabs.query({}, (tabs) => {
		const existing = tabs.find(t => {
			if (!t.url) return false;
			let currUrl = t.url.toLowerCase();
			// Tool requested
			if (callUrl.indexOf('code') !== -1 || callUrl.indexOf('!') !== -1) return currUrl.startsWith(callUrl);
			// Home requested
			if (currUrl.indexOf('code') === -1 && currUrl.indexOf('!') === -1) return callUrl.startsWith(currUrl);
			return false;
		});
		if (existing) {
			chrome.tabs.update(existing.id, { active: true });
			chrome.windows.update(existing.windowId, { focused: true });
		} else {
			chrome.tabs.create({ url: url, active: true });
		}
		window.close();
	});
}