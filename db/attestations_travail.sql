-- Table: attestations_travail
-- Objectif: enregistrer les attestations de travail d'un employé + permettre l'impression PDF.

CREATE TABLE IF NOT EXISTS `attestations_travail` (
  `id_attestation` INT NOT NULL AUTO_INCREMENT,
  `id_employe` INT NOT NULL,

  `type_attestation` VARCHAR(20) NOT NULL DEFAULT 'travail', -- travail | stage

  `reference` VARCHAR(80) NOT NULL,
  `poste` VARCHAR(120) NULL,

  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `date_delivrance` DATE NULL,
  `lieu` VARCHAR(80) NULL,

  `signataire_nom` VARCHAR(120) NULL,
  `signataire_fonction` VARCHAR(160) NULL,

  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id_attestation`),
  UNIQUE KEY `uniq_reference` (`reference`),
  KEY `idx_employe` (`id_employe`),
  KEY `idx_dates` (`date_debut`, `date_fin`)
);

-- Mise à jour si la table existe déjà (exécuter manuellement si besoin)
-- ALTER TABLE `attestations_travail`
--   ADD COLUMN `type_attestation` VARCHAR(20) NOT NULL DEFAULT 'travail' AFTER `id_employe`;

-- (Optionnel) FK si votre table employes est en InnoDB et compatible.
-- ALTER TABLE `attestations_travail`
--   ADD CONSTRAINT `fk_attestations_travail_employes`
--   FOREIGN KEY (`id_employe`) REFERENCES `employes`(`id_employe`)
--   ON DELETE CASCADE;
