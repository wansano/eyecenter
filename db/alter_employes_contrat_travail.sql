-- Ajout des champs nécessaires pour le contrat de travail (engagement)
-- À exécuter sur la base APPECv3PHP (MySQL/MariaDB)

ALTER TABLE `employes`
  ADD COLUMN `lieuNaissance` VARCHAR(255) NULL AFTER `date_naissance`,
  ADD COLUMN `nin` VARCHAR(100) NULL AFTER `lieuNaissance`,
  ADD COLUMN `expirationNin` DATE NULL AFTER `nin`,
  ADD COLUMN `engagement` INT NULL AFTER `expirationNin`,
  ADD COLUMN `typeContrat` INT NULL AFTER `engagement`;
