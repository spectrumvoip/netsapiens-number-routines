# netsapiens-number-routines

Pulls Netsapiens DID Table and loops through them looking for numbers with "addsms" in the Notes.

Then for each one found:

 1. We hit Catapult api to add the number the correct callback.
 2. We hit NetSapiens api to add the number into sms inventory
 3. Remove the addsms from the notes in Netsapiens.

