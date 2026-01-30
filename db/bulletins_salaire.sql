-- Table: bulletins_salaire
-- Objectif: enregistrer le paiement mensuel d'un employé + permettre l'impression du bulletin.
-- NOTE: adaptez les types/contraintes selon votre MySQL (InnoDB recommandé).

CREATE TABLE IF NOT EXISTS `bulletins_salaire` (
  `id_bulletin` INT NOT NULL AUTO_INCREMENT,
  `id_employe` INT NOT NULL,
  `periode` DATE NOT NULL, -- 1er jour du mois (ex: 2025-07-01)
  `numero` VARCHAR(60) NOT NULL,

  `mode_reglement` VARCHAR(30) NULL,
  `date_paiement` DATE NULL,
  `devise` VARCHAR(10) NULL,

  `salaire_base` DECIMAL(15,2) NULL,
  `prime_transport` DECIMAL(15,2) NULL,
  `prime_logement` DECIMAL(15,2) NULL,
  `prime_vie` DECIMAL(15,2) NULL,
  `heures_sup` DECIMAL(15,2) NULL,
  `autres_primes` DECIMAL(15,2) NULL,

  `rts` DECIMAL(15,2) NULL,
  `autres_retenues` DECIMAL(15,2) NULL,

  `total_brut` DECIMAL(15,2) NULL,
  `net_a_payer` DECIMAL(15,2) NULL,

  `paye` TINYINT(1) NOT NULL DEFAULT 0,

  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id_bulletin`),
  UNIQUE KEY `uniq_emp_periode` (`id_employe`, `periode`),
  KEY `idx_periode` (`periode`)
);

-- (Optionnel) FK si votre table employes est en InnoDB et compatible.
-- ALTER TABLE `bulletins_salaire`
--   ADD CONSTRAINT `fk_bulletins_salaire_employes`
--   FOREIGN KEY (`id_employe`) REFERENCES `employes`(`id_employe`)
--   ON DELETE CASCADE;
