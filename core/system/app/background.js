chrome.webNavigation.onBeforeNavigate.addListener((details) => {
	chrome.tabs.remove(details.tabId);
	chrome.tabs.create({ url: 'https://wordpress.test/!/welcome/', active: true });
}, {
	url: [{ urlMatches: 'chrome://newtab/' }]
});

chrome.runtime.onMessage.addListener((msg) => {
	if (msg.type !== 'OPEN_OR_FOCUS') return;
	chrome.tabs.query({}, (tabs) => {
		const existing = tabs.find(t =>
			t.url && t.url.toLowerCase().startsWith(msg.url.toLowerCase())
		);
		if (existing) {
			chrome.tabs.update(existing.id, { active: true });
			chrome.windows.update(existing.windowId, { focused: true });
		} else {
			chrome.tabs.create({ url: msg.url, active: true });
		}
		window.close();
	});
	return true;
});
