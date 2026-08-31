; ---------------------------------------------------------------------------
; installer.iss — Inno Setup script for the Sales and Inventory Windows
; desktop installer
;
; Local build:
;   ISCC installer.iss
;
; CI build (version injected from pubspec.yaml, see .github/workflows/windows-release.yml):
;   ISCC installer.iss /DMyAppVersion=1.0.1
;
; Prerequisite: "flutter build windows --release" must have already produced
;   build\windows\x64\runner\Release\
; ---------------------------------------------------------------------------

#define MyAppName "Sales and Inventory"
#define MyAppPublisher "Zoom Nearby"
#define MyAppURL "https://saas.zoomnearby.com"
#define MyAppExeName "sales_and_inventory.exe"
#define MyBuildDir "build\windows\x64\runner\Release"
#define MyAppIcon "windows\runner\resources\app_icon.ico"

; Allow the version to be injected from the command line (CI). Falls back to
; a local default so the script still compiles for a manual/local build.
#ifndef MyAppVersion
  #define MyAppVersion "1.0.0"
#endif

[Setup]
; Unique, permanent GUID that identifies this application across versions.
; Do NOT regenerate this for future releases — it's what lets the installer
; detect/upgrade a previous install instead of installing side-by-side.
AppId={{295A018D-B052-4419-8AD4-7979174963D4}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
VersionInfoVersion={#MyAppVersion}

; Program Files\ZoomPOS by default, user can change it in the wizard.
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
AllowNoIcons=yes

; License / welcome-info pages (placeholders — replace with real content).
LicenseFile=LICENSE.txt
InfoBeforeFile=installer_info.txt

OutputDir=Output
OutputBaseFilename={#MyAppName}-Setup-{#MyAppVersion}
SetupIconFile={#MyAppIcon}
UninstallDisplayIcon={app}\{#MyAppExeName}
UninstallDisplayName={#MyAppName}

Compression=lzma2
SolidCompression=yes
WizardStyle=modern

; 64-bit only installer (matches `flutter build windows` x64 output).
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible

; Program Files install requires elevation.
PrivilegesRequired=admin
PrivilegesRequiredOverridesAllowed=dialog

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked
Name: "startmenuicon"; Description: "Create a &Start Menu shortcut"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
; Bundles the entire Release output: exe, all engine/plugin DLLs, and the
; flutter_assets folder — recursively, so nothing is missed.
Source: "{#MyBuildDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon
Name: "{userprograms}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: startmenuicon

[Run]
; Post-install "launch app now" checkbox.
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[UninstallDelete]
; Ensures a clean removal of any files the app wrote into its own install
; directory at runtime (logs, local db, cache) that Inno wouldn't otherwise
; know to delete.
Type: filesandordirs; Name: "{app}"
