<?php
/**
 * SMT DU SAHEL - Configuration
 * Adapter ces valeurs a votre installation XAMPP si besoin.
 */

declare(strict_types=1);

const APP_NAME     = 'SMT DU SAHEL';
const APP_BASELINE = 'Fabrication et vente de meubles bois & fer';
const APP_CURRENCY = 'TND';

// --- Base de donnees (XAMPP par defaut) ---
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'smt_du_sahel';
const DB_USER = 'root';
const DB_PASS = '';

// --- Securite JWT ---
// Changez cette cle sur un serveur de production.
const JWT_SECRET   = 'smt-du-sahel-cle-secrete-a-changer-2026';
const JWT_ISSUER   = 'smt-du-sahel';
const JWT_TTL      = 60 * 60 * 8; // 8 heures
const JWT_COOKIE   = 'smt_token';

date_default_timezone_set('Africa/Tunis');
