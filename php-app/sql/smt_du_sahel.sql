-- ============================================================
-- SMT DU SAHEL - Base de donnees (MySQL / MariaDB - XAMPP)
-- Importer ce fichier dans phpMyAdmin (onglet Importer)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `smt_du_sahel`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smt_du_sahel`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_log`, `recus`, `lignes_vente`, `ventes`, `clients`,
  `paies`, `presences`, `employes`, `lignes_commande`, `commandes`,
  `mouvements_stock`, `fournitures`, `fournisseurs`, `utilisateurs`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Utilisateurs / roles
-- ------------------------------------------------------------
CREATE TABLE `utilisateurs` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nom`           VARCHAR(80)  NOT NULL,
  `prenom`        VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL UNIQUE,
  `mot_de_passe`  VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','achats','stock','rh','commercial') NOT NULL DEFAULT 'commercial',
  `actif`         TINYINT(1)   NOT NULL DEFAULT 1,
  `cree_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Achats / Stock
-- ------------------------------------------------------------
CREATE TABLE `fournisseurs` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nom`                 VARCHAR(150) NOT NULL,
  `telephone`           VARCHAR(40)  DEFAULT NULL,
  `adresse`             VARCHAR(255) DEFAULT NULL,
  `specialite`          VARCHAR(120) DEFAULT NULL,
  `conditions_paiement` VARCHAR(120) DEFAULT NULL,
  `cree_le`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `fournitures` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `fournisseur_id` INT DEFAULT NULL,
  `nom`            VARCHAR(150) NOT NULL,
  `type`           VARCHAR(80)  DEFAULT NULL,
  `unite`          VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  `quantite`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `seuil_critique` DECIMAL(12,2) NOT NULL DEFAULT 3,
  `prix_unitaire`  DECIMAL(12,3) NOT NULL DEFAULT 0,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_fourniture_fournisseur` FOREIGN KEY (`fournisseur_id`)
    REFERENCES `fournisseurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `mouvements_stock` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `fourniture_id` INT NOT NULL,
  `type`          ENUM('entree','sortie') NOT NULL,
  `quantite`      DECIMAL(12,2) NOT NULL,
  `motif`         VARCHAR(255) DEFAULT NULL,
  `utilisateur_id` INT DEFAULT NULL,
  `cree_le`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mvt_fourniture` FOREIGN KEY (`fourniture_id`)
    REFERENCES `fournitures`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mvt_user` FOREIGN KEY (`utilisateur_id`)
    REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Commandes (fournisseurs et clients)
-- ------------------------------------------------------------
CREATE TABLE `clients` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `nom`       VARCHAR(150) NOT NULL,
  `telephone` VARCHAR(40)  DEFAULT NULL,
  `adresse`   VARCHAR(255) DEFAULT NULL,
  `cree_le`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `commandes` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `reference`      VARCHAR(30) NOT NULL UNIQUE,
  `type`           ENUM('fournisseur','client') NOT NULL,
  `fournisseur_id` INT DEFAULT NULL,
  `client_id`      INT DEFAULT NULL,
  `date_commande`  DATE NOT NULL,
  `statut`         ENUM('en_attente','en_cours','livree','annulee') NOT NULL DEFAULT 'en_attente',
  `commentaire`    TEXT DEFAULT NULL,
  `total`          DECIMAL(12,3) NOT NULL DEFAULT 0,
  `utilisateur_id` INT DEFAULT NULL,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cmd_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cmd_client`      FOREIGN KEY (`client_id`)      REFERENCES `clients`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_cmd_user`        FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `lignes_commande` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id`   INT NOT NULL,
  `designation`   VARCHAR(200) NOT NULL,
  `quantite`      DECIMAL(12,2) NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(12,3) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_ligne_cmd` FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Personnel / Paie
-- ------------------------------------------------------------
CREATE TABLE `employes` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nom`           VARCHAR(80) NOT NULL,
  `prenom`        VARCHAR(80) NOT NULL,
  `poste`         VARCHAR(100) DEFAULT NULL,
  `telephone`     VARCHAR(40)  DEFAULT NULL,
  `salaire_base`  DECIMAL(12,3) NOT NULL DEFAULT 0,
  `date_embauche` DATE DEFAULT NULL,
  `actif`         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `presences` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `employe_id`    INT NOT NULL,
  `date_jour`     DATE NOT NULL,
  `statut`        ENUM('present','absent','conge','retard') NOT NULL DEFAULT 'present',
  `heure_arrivee` TIME DEFAULT NULL,
  `heure_depart`  TIME DEFAULT NULL,
  UNIQUE KEY `uniq_presence` (`employe_id`,`date_jour`),
  CONSTRAINT `fk_presence_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `paies` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `employe_id`       INT NOT NULL,
  `mois`             TINYINT NOT NULL,
  `annee`            SMALLINT NOT NULL,
  `jours_ouvrables`  INT NOT NULL DEFAULT 26,
  `jours_travailles` INT NOT NULL DEFAULT 0,
  `salaire_calcule`  DECIMAL(12,3) NOT NULL DEFAULT 0,
  `primes`           DECIMAL(12,3) NOT NULL DEFAULT 0,
  `deductions`       DECIMAL(12,3) NOT NULL DEFAULT 0,
  `net_a_payer`      DECIMAL(12,3) NOT NULL DEFAULT 0,
  `date_paiement`    DATE DEFAULT NULL,
  UNIQUE KEY `uniq_paie` (`employe_id`,`mois`,`annee`),
  CONSTRAINT `fk_paie_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Ventes
-- ------------------------------------------------------------
CREATE TABLE `ventes` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`      INT DEFAULT NULL,
  `date_vente`     DATE NOT NULL,
  `total`          DECIMAL(12,3) NOT NULL DEFAULT 0,
  `utilisateur_id` INT DEFAULT NULL,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_vente_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vente_user`   FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `lignes_vente` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `vente_id`      INT NOT NULL,
  `designation`   VARCHAR(200) NOT NULL,
  `quantite`      DECIMAL(12,2) NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(12,3) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_ligne_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `recus` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `vente_id`      INT NOT NULL,
  `numero`        VARCHAR(30) NOT NULL UNIQUE,
  `date_emission` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_recu_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tracabilite
-- ------------------------------------------------------------
CREATE TABLE `audit_log` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `utilisateur_id` INT DEFAULT NULL,
  `action`         VARCHAR(60) NOT NULL,
  `entite`         VARCHAR(60) NOT NULL,
  `entite_id`      INT DEFAULT NULL,
  `details`        VARCHAR(500) DEFAULT NULL,
  `cree_le`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Donnees initiales
-- Mots de passe de tous les comptes ci-dessous : Smt2026!
-- ------------------------------------------------------------
INSERT INTO `utilisateurs` (`nom`,`prenom`,`email`,`mot_de_passe`,`role`) VALUES
('Sahel','Admin','admin@smtdusahel.tn','$2y$12$WfoBhbKNNe2WvBzfZXGdhe7ndnjHoEpbkLLLRrE1IRboWUYWOeP86','admin'),
('Ben Ali','Achats','achats@smtdusahel.tn','$2y$12$WfoBhbKNNe2WvBzfZXGdhe7ndnjHoEpbkLLLRrE1IRboWUYWOeP86','achats'),
('Trabelsi','Stock','stock@smtdusahel.tn','$2y$12$WfoBhbKNNe2WvBzfZXGdhe7ndnjHoEpbkLLLRrE1IRboWUYWOeP86','stock'),
('Jouini','RH','rh@smtdusahel.tn','$2y$12$WfoBhbKNNe2WvBzfZXGdhe7ndnjHoEpbkLLLRrE1IRboWUYWOeP86','rh'),
('Mabrouk','Commercial','commercial@smtdusahel.tn','$2y$12$WfoBhbKNNe2WvBzfZXGdhe7ndnjHoEpbkLLLRrE1IRboWUYWOeP86','commercial');

INSERT INTO `fournisseurs` (`nom`,`telephone`,`adresse`,`specialite`,`conditions_paiement`) VALUES
('Bois du Sahel SARL','+216 73 200 100','Zone industrielle, Sousse','Bois massif et contreplaque','30 jours fin de mois'),
('Metaux Sfax','+216 74 410 220','Route de Gabes, Sfax','Fer, tubes et profiles','Comptant'),
('Quincaillerie El Amen','+216 73 330 440','Msaken, Sousse','Visserie et accessoires','15 jours');

INSERT INTO `fournitures` (`fournisseur_id`,`nom`,`type`,`unite`,`quantite`,`seuil_critique`,`prix_unitaire`) VALUES
(1,'Planche chene 200x50','Bois','pcs',24,5,85.000),
(1,'Contreplaque 18mm','Bois','pcs',12,4,62.500),
(2,'Tube carre 40x40','Fer','barre',8,6,31.000),
(2,'Tole 2mm','Fer','plaque',3,4,120.000),
(3,'Vis a bois 5x60 (boite)','Accessoire','boite',15,5,9.500),
(3,'Peinture rouge industrielle','Finition','litre',2,3,28.000);

INSERT INTO `clients` (`nom`,`telephone`,`adresse`) VALUES
('Hotel Marhaba','+216 73 240 500','Port El Kantaoui, Sousse'),
('Cafe El Medina','+216 73 220 118','Medina, Sousse'),
('Client particulier - M. Gharbi','+216 98 445 221','Monastir');

INSERT INTO `employes` (`nom`,`prenom`,`poste`,`telephone`,`salaire_base`,`date_embauche`) VALUES
('Hammami','Karim','Menuisier','+216 22 111 222',1200.000,'2022-03-01'),
('Saidi','Mohamed','Soudeur','+216 23 333 444',1150.000,'2021-09-15'),
('Ben Salah','Amine','Finition / peinture','+216 55 666 777',950.000,'2023-01-10');

INSERT INTO `commandes` (`reference`,`type`,`fournisseur_id`,`client_id`,`date_commande`,`statut`,`commentaire`,`total`,`utilisateur_id`) VALUES
('CF-1001','fournisseur',1,NULL,'2026-08-12','livree','Reapprovisionnement bois',1275.000,2),
('CC-2001','client',NULL,1,'2026-08-20','en_cours','Table 50x50 bois rouge, finition peinture',480.000,5);

INSERT INTO `lignes_commande` (`commande_id`,`designation`,`quantite`,`prix_unitaire`) VALUES
(1,'Planche chene 200x50',15,85.000),
(2,'Table 50x50 bois rouge',2,240.000);
