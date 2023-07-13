# FileDownloadR

Display a list of downloadable files without revealing the file url.

### Requirements

* MODX Revolution 2.8+
* PHP 7.4+

### Features

This MODX Extra can be used to display a list of files from a directory. The
download link for each file is hashed. This way, the full url of the file will
not be revealed and the files/directories can be located outside the webroot.
Each file can also be assigned a download counter, which is stored in a custom
database table. A single file can be uploaded using an upload form.
