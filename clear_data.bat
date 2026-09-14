@echo off
setlocal
echo ===================================================
echo   CertificateHub Database Reset Tool
echo ===================================================
echo.
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" "%~dp0clear_data.php" %*
) else (
    php "%~dp0clear_data.php" %*
)
echo.
pause
