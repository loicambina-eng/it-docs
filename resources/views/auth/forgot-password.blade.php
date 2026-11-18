{{-- Formulaire indépendant pour demander un lien de réinitialisation par courriel. --}}
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Mot de passe oublié · IT-Docs</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=20261008-4">
</head>
<body class="reset-page">
  <main class="reset-card">
    <img src="{{ asset('assets/logo-it-docs-blue.png') }}" alt="IT-Docs">
    <h1>Réinitialiser mon mot de passe</h1>
    <p>Entrez l’adresse e-mail de votre compte. La suite de la réinitialisation s’ouvrira sur une page dédiée.</p>
    <form id="forgotRequestForm">
      <label for="forgotEmailStandalone">Adresse e-mail</label>
      <input id="forgotEmailStandalone" name="email" type="email" autocomplete="email" required>
      <button id="forgotSubmit" class="primary full" type="submit">Réinitialisation</button>
      <div id="forgotMessage" role="status" aria-live="polite"></div>
      <a class="reset-link" href="{{ route('login') }}">Retourner à la fenêtre de connexion</a>
    </form>
  </main>
  <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    document.getElementById('forgotRequestForm').addEventListener('submit', async event => {
      event.preventDefault();
      const button = document.getElementById('forgotSubmit');
      const message = document.getElementById('forgotMessage');
      button.disabled = true;
      message.textContent = 'Préparation du lien…';
      try {
        const response = await fetch('/forgot-password', {
          method: 'POST',
          headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
          body: new FormData(event.currentTarget)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
        message.textContent = result.message;
        if (result.reset_url) {
          const link = document.createElement('a');
          link.href = result.reset_url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.className = 'reset-link';
          link.textContent = 'Ouvrir la page sécurisée de nouveau mot de passe';
          message.append(document.createElement('br'), link);
        }
      } catch (error) {
        message.textContent = error.message || 'La demande n’a pas abouti. Réessayez.';
      } finally {
        button.disabled = false;
      }
    });
  </script>
</body>
</html>
