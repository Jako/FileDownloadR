# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.4] - TBA

### Fixed

- Fix xPDO warning 'Encountered empty IN condition with key' if the path is empty

## [3.1.3] - 2024-01-29

### Fixed

- Supplying file descriptions does not work [#5]
- imgTypes property of the FileDownloadR snippet has no effect [#6]

## [3.1.2] - 2023-11-07

### Fixed

- Fix updgrade issue to 3.x on sites with a large download count [#4]

## [3.1.1] - 2023-09-21

### Fixed

- Fix PHP warning: Undefined array key

## [3.1.0] - 2023-07-13

### Added

- Upload a file in the current directory
- New uploadFile, uploadFileTypes, uploadGroups and uploadMaxSize property in FileDownload snippet
- New property countUserDownloads in FileDownload and FileDownloadLink snippets
- New placeholders count, count_not, count_user and count_user_not in the directory row template counting in all subdirectories
- getDir property can be empty when using mediasources
- New FileDownloadCount snippet for retrieving the download counts of a directory and its subdirectories

### Changed

- Reduce database queries if the download count is not enabled

### Fixed

- Fix browsing while using mediasources

## [3.0.2] - 2023-06-06

### Added

- Add a chunk for the tpl property of the FileDownloadLink snippet
- Add system setting filedownloadr.email_props for the FileDownloadEmail plugin

### Fixed

- Add missing mediaSourceId property to the FileDownloadLink snippet [#2]
- Use only one hash for the same file path

### Changed

- Remove the request properties of FileDownloadR from the referrer to reduce the referrer length.
- Increase the length of the referrer field in the database

## [3.0.1] - 2023-06-06

### Fixed

- Default template of FileDownloadLink doesn't work [#1]
- Compatibility with pdoTools 3.x [#1]

## [3.0.0] - 2023-05-03

### Added

- Compatibility with MODX3
- Install composer dependencies directly on the server
- Lexicon entries for all snippet properties
- Invoke plugins with $modx->invokeEvent
- FileDownloadR connector and web/file/get processor

### Fixed

- Fix PHP8 issues

### Changed

- Refactored code
- Switched from BeingTomGreen/IP-User-Location to ip2location/ipinfodb-php

### Removed

- Remove php5-utf8 class
- Internal plugin invokation

## [2.1.0] - August 31, 2016

### Added

- Add file delete support
- Additional snippet properties of the FileDownload snippet are set as placeholders in the associated chunks
- Build process with GPM

### Changed

- Inspected/refactored the current code

## [2.0.0] - 2016-03-29

### Fixed

- Fixed download from nested directories [#48]

## [2.0.0-beta2] - 2016-02-22

### Added

- Removing old data after converting

## [2.0.0-beta1] - 2016-02-19

### Added

- Show empty directory [#42]
- Add media source support [#17][#29]
- Add geolocation support

### Fixed

- Fixed anti hotlink on FURL
- Fixed unicode problems [#8][#28][#33]
- Fixed saltText comparison [#44]
- Fixed duplicate items on Group By Directory [#47]

### Changed

- Update build of fd_count, convert database [#43]
- Rename directory

## [1.1.9] - 2014-12-18

### Fixed

- Fix imageTypes
- Fixed to allow files to download within directories with Revo 2.3.2 [#39]

### Changed

- Rebuild schema
- Change _error and _output types in main class

### Removed

- Remove unused JS files
- Remove filetypes_old images

## [1.1.8] - 2014-10-06

### Fixed

- Not working with get variable in URL [#34]
- Duplicate files on multiple snippet calls [#31][#37][#38]

## [1.1.7] - 2013-09-20

### Fixed

- Fixed [[+fd.image]] only outputs the path to "assets/components/filedownloadr/img/filetypes/" without any file of the
  directory [#21]

## [1.1.6] - 2013-07-01

### Fixed

- Bugfix @CHUNK's tpl variable [#18]

## [1.1.5] - 2013-06-06

### Fixed

- Fixed build script

### Changed

- Modified the class's construct
- Updated the template parser

## [1.1.4] - 2013-02-24

### Added

- Date sorting with dateFormat property [#12]

### Changed

- Rename directories to follow the namespace

## [1.1.3] - 2013-01-02

### Changed

- Adjustments for PHP 5.4 [#11]

## [1.1.2] - 2012-11-26

### Added

- Create placeholders for dir rows and file rows [#10]
- Add &prefix property for placeholders

## [1.1.1] - 2012-11-15

### Fixed

- Fix breadcrumb [#4]

## [1.1.0] - 2012-10-20

### Fixed

- Rename the package's name to fix miscommunication with extra's naming style
- Fix the fatal error caused by the invalid package building

## [1.0.0] - 2012-09-28

### Added

- Prevent direct hyperlink from different site or referrer
- Add &fdlid for multiple snippet calls which are having &browseDirectories on the same page
- Add &encoding for internationalization
- Add &tplBreadcrumb and &breadcrumbSeparator for breadcrumbs
- Add [[+fd.hash]] for file
- Add [[+fd.url]] to replace [[+fd.link]] (deprecated)
- Add &downloadByOther (boolean) to avoid download and pass it for other script
- Add plugin examples: FormSave and Email, both require FormIt

### Changed

- Replace the first breadcrumb's link to be 'home' or any lexicon's string provided

## [1.0.0.rc5] - 2012-08-25

### Added

- Added &tplWrapperDir and &tplWrapperFile properties to provide separated wrapper templates between directories and files.

## [1.0.0.rc4] - 2021-04-30

### Fixed

- Work out with MODX's extra's submission cancellation.

## [1.0.0.rc3] - 2021-04-30

### Added

- Added trim utility to overcome the TinyMCE's whitespace bug
- Returned the empty result through template.

### Fixed

- Bugfixed fatal error caused by an empty directory

## [1.0.0.rc2] - 2012-02-25

### Added

- Added @BINDINGs to the tpl properties
- Added template for forbidden access

### Fixed

- Bugfixed the multiple usage of fileCss property

### Changed

- Refactored template parser

## [1.0.0.rc1] - 2011-08-25

### Added

### Fixed

### Changed

### Removed

- Fixed the correct realpath

## [1.0.0.b4] - 2011-09-21

### Added

- Added &directLink property to give a direct path for big files

## [1.0.0.b3] - 2011-09-20

### Added

- Added &toPlaceholder property

### Fixed

- Fixed the multicall snippets by adding individual setConfigs method

## [1.0.0.b2] - 2011-09-09

### Added

- Added FileDownloadLink snippet for a single file download
- Added parse template code for FileDownloadLink
- Added &toArray property for both snippets

## [1.0.0.b1] - 2011-08-25

### Added

- Initial release
