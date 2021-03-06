# Changelog

## v0.3.5 (2019-02-03)

* Feature: The 'isMerged' method is added to the Pack class.

## v0.3.4 (2019-02-02)

* Improvement: The 'clear' method is added to Unpack.

## v0.3.3 (2019-02-02)

* Fix: The ID2T static variable didn't set when the Unpack receives chunks from another process.
* Changes: The header limited option is removed.

## v0.3.2 (2019-01-26)

* Fix: A bug fixed in merging

## v0.3.1 (2019-01-21)

* Fix: Some bugs fixed.

## v0.3.0 (2019-01-24)

* Feature: The merging progress is added.
* Change: The 'header' event is renamed to 'unpack-header'.
* Fix: The 'unpack-header' event wasn't triggered when the pack is one chunk.

## v0.2.3 (2019-01-17)

* Feature: Added 'header' event in merging then the header of the pack is completed.

## v0.2.2 (2019-01-16)

* Feature: Add $default param to Pack::getHeaderByKey()

## v0.2.1 (2019-01-14)

* Rename 'setArrayHeader' to 'setHeaderByKey'.
* Rename 'getArrayHeader' to 'getHeaderByKey'.

## v0.2.0 (2019-01-14)

* setArrayHeader and getArrayHeader methods added.

## v0.1.0 (2019-01-03)

* First tagged release