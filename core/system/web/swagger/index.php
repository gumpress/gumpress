<!-- GumPress - MIT License -->

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Swagger</title>
	<link rel="stylesheet" type="text/css" href="./swagger-ui.css" />
	<style>
		html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
		*, *:before, *:after { box-sizing: inherit; }
		body { margin: 0;  background: #fafafa; }
		.topbar .download-url-wrapper,
		.topbar .download-url-input,
		.topbar .download-url-button { display: none !important; }
		.scheme-container, .servers, .servers-title, .scheme-container label, .scheme-container select { display: none !important; }
		.swagger-ui .info { margin-top: 30px; margin-bottom: 20px; }
	</style>
	<link rel="icon" type="image/png" href="./favicon-32x32.png" sizes="32x32" />
	<link rel="icon" type="image/png" href="./favicon-16x16.png" sizes="16x16" />
</head>
<body>
	<div id="swagger-ui"></div>
	<script src="./swagger-ui-bundle.js" charset="UTF-8"> </script>
	<script src="./swagger-ui-standalone-preset.js" charset="UTF-8"> </script>
	<script>

		window.onload = () => {

			window.ui = SwaggerUIBundle({
				url					: 'wp-openapi/',
				dom_id				: '#swagger-ui',
				deepLinking			: true,
				requestInterceptor: (req) => { req.headers['X-GumPress-Auth'] = '<?php echo getenv("GP_AUTH_SECRET") ?>'; return req; },
				presets: [
					SwaggerUIBundle.presets.apis,
					SwaggerUIStandalonePreset
				],
				plugins: [
					SwaggerUIBundle.plugins.DownloadUrl
				],
				layout: "StandaloneLayout",
				validatorUrl: null
			});

		};

	</script>
</body>
</html>
