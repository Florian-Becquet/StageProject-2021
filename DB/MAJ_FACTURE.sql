CREATE DEFINER=`root`@`localhost` EVENT `MAJ_FACTURE` ON SCHEDULE EVERY 1 MONTH STARTS '2020-01-01 03:00:00' ON COMPLETION PRESERVE ENABLE DO CALL `facturation`()