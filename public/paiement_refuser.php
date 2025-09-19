<?php
require_once '../includes/webhook.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Paiement refusé - AutomoClick</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #065f46;
    }
    .container {
      background: white;
      max-width: 600px;
      width: 90%;
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(6, 95, 70, 0.15);
      overflow: hidden;
      text-align: center;
      padding: 40px 30px;
    }
    .header {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      padding: 40px 30px;
      color: white;
      border-radius: 20px 20px 0 0;
      position: relative;
    }
    .header h1 {
      margin: 0;
      font-size: 32px;
      font-weight: 800;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .icon {
      font-size: 60px;
      margin-bottom: 20px;
      line-height: 1;
      color: #d1fae5; /* light green */
    }
    .message {
      font-size: 18px;
      margin: 30px 0 40px 0;
      color: #065f46;
      font-weight: 600;
    }
    .btn {
      display: inline-block;
      background-color: #10b981;
      color: white;
      font-weight: 700;
      padding: 15px 35px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 18px;
      transition: background-color 0.3s ease;
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
    }
    .btn:hover {
      background-color: #059669;
    }
    .footer {
      margin-top: 40px;
      font-size: 14px;
      color: #065f46;
    }
    .footer a {
      color: #10b981;
      text-decoration: underline;
    }
    @media (max-width: 600px) {
      .header h1 {
        font-size: 26px;
      }
      .message {
        font-size: 16px;
      }
      .btn {
        font-size: 16px;
        padding: 12px 30px;
      }
    }
  </style>
</head>
<body>
  <div class="container" role="main" aria-labelledby="title">
    <div class="header">
      <div class="icon" aria-hidden="true">⚠️</div>
      <h1 id="title">Paiement refusé</h1>
    </div>
    <p class="message">
      Malheureusement, votre paiement n’a pas pu être traité.<br />
      Veuillez vérifier vos informations bancaires ou réessayer plus tard.
    </p>
    <a href="/" class="btn" role="button">Retour à l’accueil</a>
    <div class="footer">
      <p>Si le problème persiste, contactez notre support.</p>
      <p><a href="https://automoclick.com/contact">Contactez-nous</a></p>
    </div>
  </div>
</body>
</html>
