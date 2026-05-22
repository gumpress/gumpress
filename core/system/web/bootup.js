document.addEventListener('DOMContentLoaded', (event) => {
	BU.dowork(document.getElementById('output'));
});

class SS
{
	static #key = ' b o o t u p ';
	static set	(val) {			  sessionStorage.setItem	(SS.#key, val); }
	static get	(	 ) { return	  sessionStorage.getItem	(SS.#key		 ); }
	static any	(	 ) { return !!sessionStorage.getItem	(SS.#key		 ); }
	static clear(	 ) {			  sessionStorage.removeItem(SS.#key		 ); }
}

class CC
{
	static #map = {
		black: 'var(--cc-black)',
		darkblue: 'var(--cc-darkblue)',
		darkgreen: 'var(--cc-darkgreen)',
		darkcyan: 'var(--cc-darkcyan)',
		darkred: 'var(--cc-darkred)',
		darkmagenta: 'var(--cc-darkmagenta)',
		darkyellow: 'var(--cc-darkyellow)',
		gray: 'var(--cc-gray)',
		darkgray: 'var(--cc-darkgray)',
		blue: 'var(--cc-blue)',
		green: 'var(--cc-green)',
		cyan: 'var(--cc-cyan)',
		red: 'var(--cc-red)',
		magenta: 'var(--cc-magenta)',
		yellow: 'var(--cc-yellow)',
		white: 'var(--cc-white)'
	}

	static ccss(name)
	{
		return CC.#map[name] || CC.#map.gray;
	}
}

class QQ
{
	static #handleMessage;
	static #queue	  = [];
	static #draining = false;

	static init(handleMessage)
	{
		QQ.#handleMessage = handleMessage;
	}

	static enqueue(msg)
	{
		QQ.#queue.push(msg);
		if (!QQ.#draining) QQ.#drain();
	}

	static clear()
	{
		QQ.#queue	 = [];
		QQ.#draining = false;
	}

	static #drain()
	{
		if (QQ.#queue.length === 0) {
			QQ.#draining = false;
			UI.save();
		}
		else {
			QQ.#draining = true;
			let msg = QQ.#queue.shift();
			try { QQ.#handleMessage(msg); } catch (e) { }
			if (QQ.#queue.length % 16 === 0) {
				requestAnimationFrame(() => QQ.#drain());
			}
			else {
				QQ.#drain();
			}
		}
	}
}

class UI
{
	static #output = null;
	static #currentRaw  = null;
	static #spinners	  = {};
	static #SPIN_FRAMES = ['|', '/', '-', '\\'];
	static #LINK_RE	  = /\[\[([^\]|]+)\|([^\]]+)\]\]/g;	//--> [[text|href]] for link (target _blank)

	static init(output)
	{
		UI.#output = output;
	}

	static spinnerShow(id, text, colorName)
	{
		if (!UI.#currentRaw) {
			UI.#currentRaw = document.createElement('div');
			UI.#currentRaw.className = 'row-raw';
			UI.#output.appendChild(UI.#currentRaw);
		}

		const textSpan = document.createElement('span');
		textSpan.style.color = CC.ccss(colorName);
		textSpan.textContent = text + ' ';
		UI.#currentRaw.appendChild(textSpan);

		const charEl = document.createElement('span');
		charEl.className = 'spinner-char';
		charEl.textContent = '|';
		UI.#currentRaw.appendChild(charEl);

		UI.scrollBottom();

		let fi = 0;
		const timer = setInterval(() => {
			charEl.textContent = UI.#SPIN_FRAMES[fi++ % UI.#SPIN_FRAMES.length];
		}, 75);

		UI.#spinners[id] = { row: UI.#currentRaw, charEl, timer };
	}

	static spinnerHide(id, ms)
	{
		const s = UI.#spinners[id];
		if (!s) return;

		clearInterval(s.timer);
		s.charEl.textContent = '';
		s.charEl.className = '';

		const ready = document.createElement('span');
		ready.className = 'spinner-ready';
		ready.textContent = 'READY';
		s.row.appendChild(ready);

		if (ms > 0) {
			const msEl = document.createElement('span');
			msEl.className = 'spinner-ms';
			msEl.textContent = ` (${String(ms).padStart(5)} ms)`;
			s.row.appendChild(msEl);
		}

		delete UI.#spinners[id];
		UI.scrollBottom();
		UI.#flushRaw();
	}

	static clear()
	{
		UI.#output.innerHTML = '';
		UI.#currentRaw = null;
		UI.#spinners = {};
	}

	static scrollBottom()
	{
		UI.#output.scrollTop = UI.#output.scrollHeight;
	}

	static #flushRaw()
	{
		UI.#currentRaw = null;
	}

	static write(text, colorName)
	{
		if (!UI.#currentRaw) {
			UI.#currentRaw = document.createElement('div');
			UI.#currentRaw.className = 'row-raw';
			UI.#output.appendChild(UI.#currentRaw);
		}
		if (text === '') return;

		const color = CC.ccss(colorName);
		let last = 0, m;
		UI.#LINK_RE.lastIndex = 0;

		while ((m = UI.#LINK_RE.exec(text)) !== null) {
			if (m.index > last) {
				const span = document.createElement('span');
				span.style.color = color;
				span.textContent = text.slice(last, m.index);
				UI.#currentRaw.appendChild(span);
			}
			const a = document.createElement('a');
			a.className = 'console-link';
			a.href = m[2];
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = m[1];
			UI.#currentRaw.appendChild(a);
			last = m.index + m[0].length;
		}

		if (last < text.length) {
			const span = document.createElement('span');
			span.style.color = color;
			span.textContent = text.slice(last);
			UI.#currentRaw.appendChild(span);
		}

		UI.scrollBottom();
	}

	static writeLine(text, colorName)
	{
		UI.write(text, colorName);
		UI.#flushRaw();
	}

	static save()
	{
		SS.set(UI.#output.innerHTML);
	}

	static load()
	{
		const saved = SS.get();
		if (saved) {
			UI.#output.innerHTML = saved;
		}
		else {
			const el = document.createElement('div');
			el.className = 'row-raw';
			el.style.color = 'var(--cc-darkgray)';
			el.textContent = '│ no connection to server';
			UI.#output.appendChild(el);
		}
	}
}

class BU
{
	static ws = null;

	static dowork(output)
	{
		UI.init(output);
		QQ.init(message => BU.#handleMessage(message));
		BU.ws = new WebSocket(WS_URL);

		const fallback = setTimeout(() => {

			BU.ws.onopen = null;
			BU.ws.onmessage = null;
			BU.ws.onerror = null;
			BU.ws.onclose = null;

			UI.load();

		}, SS.any() ? 500 : 15000);

		BU.ws.onopen = () => {
			clearTimeout(fallback);
			BU.ws._ping = setInterval(() => {
				if (BU.ws.readyState === WebSocket.OPEN) BU.ws.send(JSON.stringify({ type: 'ping' }));
			}, 15000);
		};

		BU.ws.onmessage = ({ data }) => {
			let msg;
			try { msg = JSON.parse(data); }
			catch { UI.writeLine(data, 'gray'); return; }

			if (msg.type === 'session_start') {
				UI.clear();
				QQ.clear();
				SS.clear();
				return;
			}

			if (msg.type === 'flush_ack') {
				BU.ws.send(JSON.stringify({ type: 'flush_ok' }));
				return;
			}

			QQ.enqueue(msg);
		};

		BU.ws.onerror = () => { };

		BU.ws.onclose = () => {
			clearInterval(BU.ws._ping);
		};
	}

	static #handleMessage(message)
	{
		switch (message.type) {
			case 'write':
				UI.write(message.text || '', message.color || 'gray');
				break;

			case 'writeline':
				UI.writeLine(message.text || '', message.color || 'gray');
				break;

			case 'spinner_start':
				UI.spinnerShow(message.id, message.text || '', message.color || 'gray');
				break;

			case 'spinner_done':
				UI.spinnerHide(message.id, message.ms || 0);
				break;

			case 'clear':
				UI.clear();
				break;
		}
	}
}
