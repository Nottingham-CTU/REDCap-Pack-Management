# REDCap Pack Management
This REDCap module provides support for defining and loading lists of packs to be assigned to
records. Packs can be optionally issued to DAGs in real time and can be set to expire or be marked
as invalid if necessary.

This module can be used for various types of packs, such as treatment packs (which could be assigned
to records using the [Minimization](https://github.com/Nottingham-CTU/REDCap-Minimization) module),
or vouchers (which could be assigned to records upon completion of a form). A detailed comparison of
this module and the built-in REDCap Randomization feature is below.

## Setting up Pack Categories
Pack categories can be set up by users with project design and setup rights (or the module specific
permission if enabled). Each pack category is independent of the others and as many categories can
be created as required.

To add/edit categories, use the *Pack Management: Configure Categories* link under
*External Modules* in the left hand menu.

Pack categories have the following options:
* Unique Category Name<br>
  This is the unique name of the pack category used by the system
* Enabled
* Assignment Trigger<br>
  This is the condition under which pack assignment occurs, one of:
  * Automatic - *trigger once the logic becomes true*
  * Form submission - *trigger on submission of a specific form, with optional logic*
  * Minimization - *will be triggered by the
    [Minimization](https://github.com/Nottingham-CTU/REDCap-Minimization) module*
  * Selection - *allow the user to manuallly select the pack (more details below)*
* If no pack for minimized allocation *(minimization trigger only)*<br>
  Specify what to do if the only available packs do not match the minimized allocation
  * Skip allocation - *try all the allocations in order of minimization until a pack is found*
  * Prohibit minimization - *prevent minimization from taking place until packs for all allocations
    are available*
* Trigger Logic<br>
  Conditional logic which must be satisfied for the pack assignment to take place.
* Packs to be issued to DAGs<br>
  If yes, packs must be issued to DAGs before they can be used, and packs will only be assigned to
  records in the same DAG.
* Require confirmation of receipt of pack<br>
  If packs are issued to DAGs, setting this to yes will require the packs be marked as received.
  This is useful for packs that may spend time in transit.
* Packs to be grouped into blocks<br>
  If yes, packs must be given a block ID and some actions (e.g. issuing packs to DAGs) can only be
  performed on entire blocks at a time.
* Packs have expiry dates<br>
  If yes, each pack must be given an expiry date, after which it cannot be used.
* Pack expiry buffer<br>
  Set the minimum time in hours which must be remaining before a pack expires in order for it to be
  used. If set to 0, a buffer of 10 minutes will be applied.
* Field to store pack ID<br>
  This is the field on the record to which the pack ID will be saved when a pack is assigned.<br>
  *Note if the pack ID field is present on multiple events/instances, multiple packs can be assigned
  to the record. See the section below on handling multiple events/instances for more information.*
* Field to store pack assignment date<br>
  This is the field on the record to which the date/time of pack assignment will be saved.<br>
  *(not applicable for minimization trigger)*
* Field to store number of remaining packs<br>
  The number of remaining packs following the assignment will be saved to this field. This will be
  the number of valid packs which could be assigned to similar records, taking into account DAG
  etc. This option may be useful for triggering alerts when the number of available packs is low.
* Pack value must match field *or* Randomization field<br>
  This option will restrict pack selection to those with a *value* which matches the value of the
  specified field.<br>
  For the minimization trigger, this option selects the *randomization field*. The pack value will
  always need to match the minimized allocation. The minimization module will use the minimization
  triggered category with the corresponding randomization field, or if there is no such category,
  the minimization category with no set randomization field.
- Additional pack fields<br>
  These are extra fields which can be defined to store more data about each pack. They can
  optionally be configured to store the data into record fields on pack assignment.<br>
  * Additional pack field name<br>
    Unique name of the additional field, used when uploading pack data from a file.
  * Additional pack field label<br>
    Name of the additional field displayed within the pack management module.
  * Additional pack field type<br>
    Data type of the field, can be one of Text box, Date, Datetime, Time, Integer.
  * Field to store additional pack field<br>
    The field on the record where the data will be stored upon pack assignment.
* User role options<br>
  Enter role names (one per line) to provide users with that role with the corresponding privilege.
  * User roles that can view packs<br>
    Roles listed here can view the list of packs, limited by DAG if packs are issued to DAGs and the
    user is in a DAG. Where confirmation of receipt of pack is required, users that can view packs
    can acknowledge the packs as received.
  * User roles that can issue packs to DAGs<br>
    Roles listed here can view the list of packs and issue packs to any DAG, and (if the packs are
    not assigned) re-issue previously issued packs to a new DAG or un-issue packs from a DAG.
  * User roles that can mark packs as invalid<br>
    Roles listed here can view the list of packs and mark packs as invalid. Any pack marked as
    invalid will be deemed unusable and will not be allocated.
  * User roles that can manually (re)assign packs to records<br>
    Roles listed here can view the list of packs and un-assign a pack from a record, (re)assign a
    pack to a new record, and swap pack assignments between two records.
  * User roles that can add packs<br>
    Roles listed here can add new packs, either one at a time or in bulk by uploading a list of
    packs.
  * User roles that can edit/delete packs<br>
    Roles listed here can see detailed information about packs, edit any data on the packs and
    delete packs.

## Adding packs to a pack category
Packs can be added to a category by users with project design and setup rights (or the module
specific permission if enabled), or by users in a role which has permission to add packs for that
category.

To add packs, use the *Pack Management* link under *External Modules* in the left hand menu and
choose *Add Packs* next to the category you want to add packs to.

There are two methods for adding packs to a pack category:

* Add single pack - *add packs one at a time by completing each field*
* Add multiple packs - *upload a file with a list of packs*

If you choose to add multiple packs, details of the file format are displayed on the page. You will
need to ensure all required fields are included in the file for upload, this may include additional
pack fields if these have been defined for the pack category.

## Viewing, Issuing, Marking and Assigning packs
For a given pack category, the list of packs can be viewed by users with project design and setup
rights (or the module specific permission if enabled), or by users in a role which has at least one
of the following permissions:

* View packs
* Issue packs to DAGs
* Mark packs as invalid
* Manually (re)assign packs to records
* Edit/delete packs

The *view packs* permission also permits acknowledging packs as received, if the pack category is
set up to require packs to be issued to DAGs and acknowledged as received.

If packs are to be issued to DAGs, users in a DAG will only see packs which have been issued to
their DAG.

The options to acknowledge packs as received, issue packs to DAGs, mark packs as invalid and
manually (re)assign packs to records are displayed at the bottom of the page below the list of
packs.

To perform an action with pack(s), first select the pack(s) in the list using the checkboxes and
then find the option you want to use at the bottom of the page. Hold down the shift key while
selecting packs to select a range. Note that some options will only be available to use on complete
block(s) of packs (if blocks are enabled) and/or packs which are not yet assigned to a record.

## Handling multiple events/instances
If the REDCap project contains multiple events or multiple instances, such that the Pack ID field is
present more than once in a record, it is possible to assign more than one pack to each record. The
assignment trigger will be evaluated on a per-event and per-instance basis if applicable.

**Automatic trigger:** This will evaluate the logic for all relevant events and instances. If you
want to limit the trigger to specific events/instances, you can use smart variables in the logic to
achieve this.<br>
For example: `[event-name] = 'baseline_arm_1' and [current-instance] = 2`

**Form submission and selection triggers:** This will trigger for the event/instance of the
submitted form. Use the trigger logic to limit to specific events/instances.<br>
For the form submission trigger, the Pack ID field does not have to exist on the triggering form,
but it must exist on the same event/instance.

**Minimization trigger:** This will apply to the event used for minimization.

## Using the selection trigger
When a form or survey page is loaded with a pack ID field triggered on selection, if the existing
value of the field is empty, the field will be rendered as a drop down list with all the available
packs as options. When the form or survey is submitted with a pack selected, this will then trigger
the pack assignment process.

If the form is loaded with a pack ID field which contains a value, this will be rendered as normal.
You will probably want to use conditional `@READONLY` or `@HIDDEN` action tags to prevent the pack
field from being edited after pack assignment.

The selection trigger supports the REDCap mobile app. If a value is entered into the pack ID field
in the mobile app which matches a valid unassigned pack then that pack will be assigned when the
data is sent to the server. If packs are labelled with barcodes the `@BARCODE-APP` action tag can
be used to make pack selection in the app easier.

## Comparison with REDCap Randomization
REDCap contains a built-in randomization feature which was improved in version 14.7.0 and this
provides some similar functionality to this Pack Management module. Please refer to the feature
comparison table below to help you decide which best meets your needs.

|Feature|REDCap Randomization|Pack Management|
|---|:---:|:---:|
|Intended use case|Randomization|Flexible|
|Multiple categories|Since version 14.7.0|Yes|
|Integration with Minimization module|Planned|Yes|
|Issue packs to DAGs|When generating list|At any time as required|
|Acknowledge packs as received by DAG|No option for this|Yes if enabled|
|Mark packs as invalid|REDCap administrator|Defined roles|
|Manually (re)assign packs|REDCap administrator|Defined roles|
|Add/edit/delete packs|In development status,<br>or by REDCap administrator|Any time by defined roles|
|Pack expiry|No|Yes|
|Store additional fields|No, ID/allocation only|Yes, with custom fields|
|Separate lists for development and production|Yes|No|
|Limit assignment trigger by role|Randomize privilege|Smart variables in trigger logic|
|Click button to assign|Randomize button option|Not available<br>(unless triggered by Minimization)|
|Specific pack can be selected|No, automatic assignment only|Yes, using selection trigger|
|Obtain count of remaining packs|Not possible|Can populate field|

