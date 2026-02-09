-- Table: avis_information
-- Stocke des avis d'information (objet + contenu HTML issu d'un éditeur WYSIWYG)

CREATE TABLE IF NOT EXISTS `avis_information` (
  `id_avis` INT NOT NULL AUTO_INCREMENT,
  `objet` VARCHAR(200) NOT NULL,
  `contenu` LONGTEXT NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_avis`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
