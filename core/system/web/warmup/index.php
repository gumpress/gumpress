<!-- GumPress - MIT License -->

<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<title>Warming up WordPress</title>
	<style>

		body { 
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
			display: flex; justify-content: center; align-items: center; 
			height: 100vh; margin: 0; 
		}

		.card { 
			text-align: center; 
			font-weight: 600;
			padding: 30px 40px;
			border-radius: 12px; 
			box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 20px;
		}

		.card p { margin: 0; font-size: 1.1em; }
		.spinner { margin: 0; } 

		#subtext {
			font-size: 0.8em;
			font-style: italic;
			font-weight: 400;
			margin: 0 0 0 0;
			opacity: 0.7;
			display: block;
		}

		/* Light Mode */
		body { background: #f9f9f9; }
		.card { background: white; border: 1px solid #eee; }
		p { color: #646970; }
		.spinner { border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; }

		/* Dark Mode */
		@media (prefers-color-scheme: dark) {
			body { background: #121212; }
			.card { background: #1e1e1e; border: 1px solid #333; }
			p { color: #b0b0b0; }
			.spinner { border: 4px solid #333; border-top: 4px solid #0073aa; }
		}

		@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
		.spinner { border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }

		@view-transition {
			navigation: auto;
		}

	</style>
</head>
<body>
	<div class='card'>
		<div class='spinner'></div>
		<p>Warming up WordPress</p>
		<p id='subtext'>First launch may take a moment.<br>Subsequent starts will be faster.</p>
	</div>
	<script>
		const startUrl	= '<?php getenv('GP_WORDPRESS_ENDPOINT') ?>/wp-admin/';
		const probeUrl	= '<?php getenv('GP_WORDPRESS_ENDPOINT') ?>/?!warmup!'; 
		async function checkReady() {
			try {
				const response = await fetch(probeUrl, { method: 'HEAD', headers: { 'HTTP_X_GUMPRESS_WARMUP': 'true' }, cache: 'no-store' });
				window.location.href = startUrl;
			}
			catch (e) {
				setTimeout(checkReady, 250);
			}
		}
		checkReady();
	</script>
</body>
</html>
