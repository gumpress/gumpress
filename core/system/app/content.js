window.addEventListener("message", (event) => {
	if (event.source !== window) return;
	if (typeof event.data !== "string") return;
	// ᐅ (U+1405, Canadian Syllabics West-Cree Po)
	if (event.data.startsWith('ᐅ')) {
		const url = event.data.substring(1);
		chrome.runtime.sendMessage({ type: "OPEN_OR_FOCUS", url: url });
	}
});
