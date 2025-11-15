@echo off
:: Configurations de base de données
set "DB_USER=root"
set "DB_PASSWORD=rLPDBBrlgwepw*wY"
set "DB_NAME=eyescenter"
set "DB_HOST=localhost"

:: Dossier de sauvegarde
set "BACKUP_DIR=D:\BACKUP"
:: Date de sauvegarde
set "DATE=%date:~6,4%-%date:~3,2%"

:: Fichier de sauvegarde
set "BACKUP_FILE=%BACKUP_DIR%\%DB_NAME%_%DATE%.sql"
:: Fichier compressé en ZIP
set "ZIP_FILE=%BACKUP_DIR%\%DB_NAME%_%DATE%.zip"

:: Chemin complet vers mysqldump
set "MYSQLDUMP_PATH=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"

:: Créer le dossier de sauvegarde s'il n'existe pas
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:: Exécuter la commande mysqldump
echo Sauvegarde de la base de données %DB_NAME% en cours...
"%MYSQLDUMP_PATH%" -u %DB_USER% -p%DB_PASSWORD% -h %DB_HOST% %DB_NAME% > "%BACKUP_FILE%"

:: Vérification de succès de l'exportation
if %errorlevel% equ 0 (
    echo Sauvegarde SQL réussie : %BACKUP_FILE%
    
    :: Compresser en ZIP le fichier de sauvegarde
    powershell Compress-Archive -Path "%BACKUP_FILE%" -DestinationPath "%ZIP_FILE%"
    
    :: Vérification de succès de la compression
    if %errorlevel% equ 0 (
        echo Archive ZIP créée avec succès : %ZIP_FILE%
        :: Supprimer le fichier .sql après compression
        del "%BACKUP_FILE%"
    ) else (
        echo Erreur lors de la création de l'archive ZIP.
    )
) else (
    echo Erreur lors de la sauvegarde de la base de données.
)

:: Suppression des fichiers ZIP de plus de 10 jours
echo Suppression des fichiers ZIP de plus de 10 jours dans %BACKUP_DIR%...
forfiles /p "%BACKUP_DIR%" /s /m *.zip /d -10 /c "cmd /c del @file"

echo Opération terminée.