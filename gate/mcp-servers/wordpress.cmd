@echo off
"%~dp0..\..\user\temp\gate\mcp-servers\wordpress-proxy.cmd" %1

:: Claude Desktop sample:
::
:: {
::    "mcpServers": {
::       "wordpress": {
::          "command": "B:\\gate\\mcp-servers\\wordpress.cmd"
::       }
::    }
:: }
::
:: Test Message: "Verify the connection to wordpress via local mcp server"
