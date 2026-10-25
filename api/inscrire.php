<?php
header('Content-Type: application/json; charset=utf-8');

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/db.php';

use Cloudinary\Cloudinary;

function reponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reponse(405, ['success' => false, 'message' => 'Méthode non autorisée.']);
}

// 1. Recevoir les données
$nom    = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$ville  = trim($_POST['ville'] ?? '');

// 2. Vérifier les données
if ($nom === '' || $prenom === '' || $ville === '') {
    reponse(400, ['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
}
if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100 || mb_strlen($ville) > 100) {
    reponse(400, ['success' => false, 'message' => 'Champ trop long (100 caractères max).']);
}

// 2 bis. Vérifier le fichier image
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    reponse(400, ['success' => false, 'message' => 'Veuillez sélectionner une photo valide.']);
}
$tmp = $_FILES['photo']['tmp_name'];
if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
    reponse(400, ['success' => false, 'message' => 'Photo trop lourde (5 Mo max).']);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    reponse(400, ['success' => false, 'message' => 'Format accepté : JPG, PNG ou WEBP.']);
}

try {
    // 3. Envoyer la photo à Cloudinary (lit CLOUDINARY_URL)
    $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));
    $upload = $cloudinary->uploadApi()->upload($tmp, ['folder' => 'stagiaires']);

    // 4. URL sécurisée
    $photoUrl = $upload['secure_url'];

    // 5. Insérer dans MySQL
    $pdo  = getDatabase();
    $stmt = $pdo->prepare(
        'INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (:nom, :prenom, :ville, :photo)'
    );
    $stmt->execute([
        ':nom' => $nom, ':prenom' => $prenom, ':ville' => $ville, ':photo' => $photoUrl,
    ]);

    // 6. Réponse JSON
    reponse(201, ['success' => true, 'message' => 'Stagiaire inscrit avec succès.', 'photo' => $photoUrl]);
} catch (Throwable $e) {
    error_log($e->getMessage());
        $u = parse_url((string) getenv('CLOUDINARY_URL'));
    reponse(500, ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()
        . ' | cloud=' . ($u['host'] ?? 'vide')
        . ' | key=' . ($u['user'] ?? 'vide')
        . ' | secret_len=' . strlen($u['pass'] ?? '')]);
}
