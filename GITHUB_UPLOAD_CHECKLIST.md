# Before pushing to GitHub

- [ ] Verify `local_config.php` is not present in the project copy.
- [ ] Never add the original SQL dump or log files.
- [ ] Run `composer install` locally after setting up the project.
- [ ] Import `database/schema.sql` into a fresh test database.
- [ ] Retest authentication and booking flows; this sanitized copy has not been end-to-end tested.
- [ ] Rotate any SMTP app password that was present in the original archive.
- [ ] Check the repository files and Git changes before making the repository public.
- [ ] Do not upload real customer, account, or health information.
