# CameraWebService

**Version 3.2.0** — Webbaseret kameraservice til Raspberry Pi.

Henter snapshots fra RTSP/RTSPS-streams med `ffmpeg` og uploader direkte fra RAM — ingen permanent lokal billedfil. Understøtter op til 5 kameraer med individuelle optagelsesintervaller og pauseskemaer. Integrerer med Track Status Light Server via en offentlig kamera-URL.

---

## Hardwarekrav

- Raspberry Pi 4 (eller nyere)
- SD-kort 16 GB+
- Netværk (Ethernet anbefales)

---

## Installation

### Trin 1 — Klargør SD-kort

1. Download og installer [Raspberry Pi Imager](https://www.raspberrypi.com/software/).
2. Vælg **Raspberry Pi OS Lite (64-bit)** som operativsystem.
3. Klik på tandhjulet (⚙) eller tryk **Ctrl+Shift+X** for at åbne *Advanced options*:
   - Sæt hostname, fx `rpicam01`
   - Aktivér SSH
   - Angiv brugernavn og adgangskode
   - Konfigurér WiFi hvis du ikke bruger Ethernet
4. Skriv image til SD-kortet og sæt det i Pi'en.

### Trin 2 — Find Pi'ens IP-adresse

```bash
# Fra en anden maskine på samme netværk:
ping rpicam01.local

# Eller find IP direkte på Pi'en:
hostname -I
```

Opret SSH-forbindelse:

```bash
ssh admin@rpicam01.local
```

### Trin 3 — Opdatér systemet og installér Git

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git
```

### Trin 4 — Hent koden

```bash
cd ~
git clone https://github.com/jesperlowe/CameraWebService.git
cd CameraWebService
```

### Trin 5 — Kør installationsscriptet

```bash
sudo bash install.sh
```

Scriptet installerer automatisk:
- Python 3, `python3-venv` og `ffmpeg`
- Systembrugeren `camerawebservice`
- Koden i `/opt/CameraWebService`
- Et Python virtual environment med alle afhængigheder
- Systemd-servicen `CameraWebService` (starter ved boot)

Når installationen er færdig vises adressen til webinterfacet:

```
Webinterface: http://<pi-ip>:8080
```

### Trin 6 — Åbn webinterfacet

Gå til `http://<pi-ip>:8080` i en browser.

- Brugernavn: `admin`
- Adgangskode: `admin`

Du tvinges til at vælge en ny adgangskode ved første login.

---

## Opdatering til ny version

```bash
cd ~/CameraWebService
git pull
sudo bash install.sh
```

---

## Webinterface — oversigt

| Fane | Formål |
|------|--------|
| **Dashboard** | Status for alle kameraer — seneste upload, eventuelle fejl, mørketidsstatus, og knapper til at tage og uploade et testsnapshot med live forhåndsvisning |
| **Kameraer** | Tilføj/ret kameraer — RTSP-URL, filnavn, optagelsesinterval, per-kamera pauseskema |
| **Upload** | Upload-metode (FTP/FTPS/SFTP/WordPress), offentlig basis-URL |
| **Tidsplan** | NTP-opsætning og globale mørkeperioder (fælles fallback for alle kameraer) |
| **Indstillinger** | Tidszone og Healthchecks.io ping-URL |
| **Sprog** | Skift sprog, upload og download sprogfiler |
| **Logs** | Applikationslog direkte i browseren |
| **Backup** | Download/genopret konfiguration som XML; download WordPress-plugin som zip |

---

## Kameraer

Tjenesten understøtter op til **5 kameraer** med individuelle indstillinger:

| Indstilling | Beskrivelse |
|-------------|-------------|
| **RTSP-URL** | Stream-adresse, fx `rtsp://bruger:kode@192.168.1.100:554/stream` |
| **Filnavn** | Navn på den uploadede fil, fx `camera1.jpg` |
| **Interval** | Antal minutter mellem snapshots (minimum 1) |
| **Pauseskema** | Tidsintervaller hvor kameraet ikke optager (per kamera, se nedenfor) |

### Pauseskema (per kamera og globalt)

Hvert kamera har sit eget pauseskema. Hvis et kamera ikke har et skema, bruges de **globale mørkeperioder** fra **Tidsplan** som fallback.

Pauseskemaer understøtter natten-over-perioder (fx 22:00–06:00) og kan begrænses til bestemte ugedage.

---

## Upload-metoder

### FTP / FTPS

Vælg `ftp` eller `ftps` og udfyld host, port (`21`), brugernavn, adgangskode og remote-mappe.

### SFTP (SSH)

Vælg `sftp`. Port er typisk `22`. Du kan bruge enten adgangskode eller en privat SSH-nøgle (angiv stien til nøglefilen på Pi'en).

### WordPress REST API

Vælg `wordpress` og angiv endpoint-URL og Bearer-token (genereres i WordPress-pluginet).

### Offentlig basis-URL

Uanset upload-metode kan du angive en **offentlig basis-URL** — den rod-URL hvor billederne er tilgængelige på nettet. CameraWebService bruger denne URL til at informere Track Status Light Server om kameraets aktuelle billede.

### Offentligt kamera-API

```
GET /api/cameras
```

Returnerer (uden login) en JSON-liste over alle konfigurerede kameraer med deres beregnede offentlige billed-URL — sammensat af den offentlige basis-URL og kameraets filnavn:

```json
[
  { "id": 1, "name": "Kamera 1", "filename": "camera1.jpg", "public_url": "https://example.com/camera/camera1.jpg" }
]
```

Praktisk for andre systemer (fx Track Status Light Server) der skal slå et kameras aktuelle billed-URL op uden at kende filnavnet på forhånd.

---

## WordPress-integration

### Download plugin

Download WordPress-pluginet direkte fra webinterfacet:

**Backup → WordPress-plugin → Download camera-snapshot.zip**

Installér zippet i WordPress via **Plugins → Tilføj ny → Upload plugin**.

### Opsætning

1. Gå til **Indstillinger → Camera Snapshot** i WordPress-admin.
2. Klik **Generér token** og kopiér det genererede token.
3. Indsæt token og endpoint-URL i CameraWebService under **Upload**.

Endpoint: `https://dit-site.dk/wp-json/camera-snapshot/v1/upload`

### Shortcodes

```
[camera_snapshot]
```
Viser det seneste billede fra standardkameraet (`latest.jpg`).

```
[camera_snapshot file="camera2.jpg"]
```
Viser billedet fra et specifikt kamera. Brug det filnavn du har konfigureret i CameraWebService.

---

## Tema

Klik på solen/måne-ikonet øverst i navigationen for at skifte mellem mørkt og lyst tema. Valget huskes i browseren på tværs af sessioner.

---

## Healthchecks.io

Under **Indstillinger** kan du angive en [healthchecks.io](https://healthchecks.io/) ping-URL:

```
https://hc-ping.com/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

- Ping sendes automatisk efter hvert vellykket upload.
- `/fail` pinges ved fejl.

Giver overvågning med notifikationer hvis kameraet holder op med at uploade.

---

## Sprogfiler

Sprogfiler ligger i `language/`. Administrér dem fra **Sprog**-fanen:

- **Download** — Hent en eksisterende sprogfil som `.json` til brug som skabelon.
- **Upload** — Upload en ny eller redigeret `.json`-sprogfil direkte uden SSH.

Filen skal indeholde felterne `version`, `locale`, `name`, `nativeName`, `fallback` og `translations`.

---

## Backup og gendannelse

Under **Backup** kan du:

| Handling | Beskrivelse |
|----------|-------------|
| **Fuld backup** | XML med alle indstillinger inkl. adgangskoder og tokens |
| **Backup uden adgangskoder** | XML til deling med support — kræver nyt password ved genoprettelse |
| **Genopret** | Upload en XML-backup for at erstatte den nuværende konfiguration |
| **Download plugin** | WordPress-plugin som installationsklar `.zip`-fil |

Backuppen inkluderer: kameraer, upload-konfiguration, pauseskemaer, NTP, mørkeperioder, tidszone, sprog og Healthchecks.io URL.

---

## Netværksstyring

Hostname, IP-adresser og netværksindstillinger styres ikke fra CameraWebService. Vi anbefaler [**Cockpit**](https://cockpit-project.org/):

```bash
sudo apt install -y cockpit
sudo systemctl enable --now cockpit.socket
```

Cockpit åbnes på `http://<pi-ip>:9090`.

---

## Fejlfinding

```bash
# Vis live log
sudo journalctl -u CameraWebService -f

# Tjek servicestatus
sudo systemctl status CameraWebService

# Genstart tjenesten
sudo systemctl restart CameraWebService
```

Applikationsloggen er også tilgængelig direkte i web-UI under **Logs**.

### Ældre Hikvision-kameraer

Nogle ældre Hikvision-kameraer (fx DS-2CD2532F-I) fejler ved RTSP-forhandling med exitkode 8. CameraWebService løser dette automatisk ved at begrænse ffmpeg til video-tracks og undgå SDP-options der afvises af disse kameraer.

---

## Sikkerhed

- Konfiguration gemmes i `/opt/CameraWebService/config.json` med `chmod 600`
- Adgangskoder og tokens vises ikke i UI efter de er gemt
- Session-nøglen genereres tilfældigt ved første opstart
- Tjenesten kører som den uprivilegerede systembruger `camerawebservice`
