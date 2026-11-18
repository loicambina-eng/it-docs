<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>IT-Docs — IT-CORP</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=20261018-1">
</head>
<body>

<!-- Écran d’accueil affiché avant la connexion. La photo de fond est appliquée par app.css. -->
<section id="welcome" class="welcome-screen">
  <!-- Couche de l’image de fond ; son chemin et son cadrage sont définis dans app.css. -->
  <div class="welcome-photo"></div>
  <!-- Voile coloré placé sur la photo pour rendre le texte lisible. -->
  <div class="welcome-overlay"></div>
  <!-- Contenu de présentation de l’entreprise et de la plateforme. -->
  <div class="welcome-content">
    <!-- Logo de l’entreprise, puis nom et description de l’application. -->
    <img src="{{ asset('assets/logo-it-corp.png') }}" alt="IT-CORP" class="welcome-logo">
    <div class="welcome-brand">IT-CORP</div>
    <h1>IT-Docs</h1>
    <p>Plateforme de gestion et d’archivage<br>des documents administratifs</p>
    <div class="welcome-line"></div>
    <p class="welcome-note">Vos documents, notre priorité</p>
    <!-- Le bouton masque cet écran et affiche le formulaire de connexion. -->
    <button class="welcome-btn" onclick="showLogin()">Accéder à IT-Docs <span>→</span></button>
  </div>
</section>

<!-- CONNEXION -->
<!-- Fenêtre de connexion : le formulaire est traité par public/js/app.js. -->
<section id="login" class="auth-screen hidden">
  <div class="auth-side-only login-bg-clean">
    <div class="auth-card">
      <!-- Logo de l’application affiché au-dessus du formulaire. -->
      <img src="{{ asset('assets/logo-it-docs-blue.png') }}" alt="IT-Docs" class="login-app-logo">
      <h2>Connexion à IT-Docs</h2>
      <p class="subtext">Accédez à votre espace sécurisé</p>
      <!-- Le JavaScript intercepte l’envoi et transmet les champs à POST /login. -->
      <form id="loginForm">
        <!-- Identifiant du compte ; le navigateur vérifie le format de l’adresse e-mail. -->
        <label>Adresse e-mail</label>
        <div class="input-icon"><input id="loginEmail" type="email" autocomplete="username" required placeholder="exemple@it-corp.co"></div>
        <!-- Mot de passe du compte ; le bouton permet de l’afficher ou le masquer. -->
        <label>Mot de passe</label>
        <div class="input-icon password-field"><input id="loginPassword" type="password" autocomplete="current-password" required placeholder="Votre mot de passe"><button type="button" class="password-toggle" onclick="togglePassword('loginPassword',this)" aria-label="Afficher le mot de passe">Afficher</button></div>
        <!-- Lien vers la page de demande de réinitialisation par e-mail. -->
        <div class="login-options login-options-single"><a class="text-btn" href="{{ route('password.request') }}">Mot de passe oublié ?</a></div>
        <!-- Soumet les identifiants ; les erreurs du serveur sont affichées ci-dessous. -->
        <button class="primary full" type="submit">Se connecter <span>→</span></button>
        <p id="loginError" class="auth-feedback" role="alert" aria-live="polite"></p>
      </form>
      <!-- Permet de revenir à l’écran de présentation sans recharger la page. -->
      <button type="button" class="back-welcome" onclick="showWelcome()">← Retour à l’accueil</button>
      <small class="copyright">IT-CORP © 2026. Tous droits réservés.</small>
    </div>
  </div>
</section>

<!-- APPLICATION -->
<section id="app" class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand-row">
      <img src="{{ asset('assets/logo-it-docs-blue.png') }}" alt="IT-Docs" class="sidebar-brand-logo">
      <button id="closeMenu" class="mobile-close">×</button>
    </div>
    <nav class="main-nav">
      <button data-page="dashboard" class="active"><span>Tableau de bord</span></button>
      <button data-page="documents" data-role="document"><span>Documents</span></button>
      <button data-page="pending" data-role="assistante"><span>Documents en attente</span><span id="pendingNavCount" class="nav-count" style="display:none">0</span></button>
      <button data-page="deposit" data-role="assistante"><span>Déposer un document</span></button>
      <button data-page="archive" data-role="assistante"><span>Archivage</span></button>
      <button data-page="tiers" data-role="assistante"><span>Tiers</span></button>
      <button data-page="notifications"><span>Notifications</span><span id="notificationNavCount" class="nav-count" style="display:none">0</span></button>
      <button data-page="settings"><span>Paramètres</span></button>
      <button data-page="admin" data-role="admin" style="display:none"><span>Gestion des utilisateurs</span></button>
    </nav>
    <div class="sidebar-bottom">
      <button id="homeButton" type="button" onclick="openPage('dashboard')"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/></svg></span><span>Accueil</span></button>
      <button id="logout"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M16 16l5-4-5-4m5 4H9"/></svg></span><span>D&eacute;connexion</span></button>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="backdrop"></div>

  <main class="main">
    <header class="topbar">
      <button id="menu" class="menu-btn" type="button" aria-label="Afficher ou r?duire le menu" aria-expanded="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      <button class="icon-btn notification-btn" onclick="openPage('notifications')" title="Notifications"><svg class="bell-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span id="notificationBadge" class="notification-badge hidden">0</span></button>
      <div class="profile-mini" onclick="openPage('settings')">
        <div class="avatar">LM</div><div class="profile-mini-text"><b id="topProfileName">MBINA MOUSSAVOU Jeannie Loïca</b><small id="topProfileRole">Assistante de direction</small></div><span class="chevron">⌄</span>
      </div>
    </header>

    <div class="content">
      <!-- DASHBOARD -->
      <div id="dashboard" class="page">
        <div id="standardDashboard">
          <div class="page-head">
            <div><small class="eyebrow">VUE D'ENSEMBLE</small><h1>Tableau de bord</h1><p id="standardGreeting">Bonjour</p><span class="description">Voici un aperçu de votre activité</span></div>
          </div>
          <div class="stats">
            <article class="stat-card"><div><span>Total documents</span><strong id="standardTotalCount">0</strong><em>Documents enregistrés</em></div></article>
            <article class="stat-card"><div><span>Documents archivés</span><strong id="standardArchivedCount">0</strong><em>Documents classés</em></div></article>
            <article class="stat-card"><div><span>Documents en attente</span><strong id="standardPendingCount">0</strong><em>À traiter</em></div></article>
          </div>
          <div class="dashboard-grid admin-feature-grid assistant-feature-grid">
            <article class="panel chart-panel assistant-donut-panel">
              <div class="panel-head"><div><h3>Répartition des documents</h3><p>Documents par catégorie</p></div></div>
              <div class="donut-area">
                <div class="donut"><div><strong id="assistantDocumentTotal">0</strong><span>documents</span></div></div>
                <div class="legend" id="assistantCategoryLegend"></div>
              </div>
            </article>
            <article class="panel recent-panel"><div class="panel-head"><div><h3>Documents récents</h3><p>Derniers documents déposés</p></div><button class="text-btn" onclick="openPage('documents')">Voir tout</button></div><div id="recent"></div></article>
          </div>
          <div class="dashboard-grid" style="grid-template-columns:1fr;margin-top:16px">
            <article class="panel chart-panel assistant-activity-panel">
              <div class="panel-head"><div><h3>Activité documentaire par mois</h3><p>Nombre de documents traités</p></div></div>
              <div class="admin-chart assistant-month-chart" aria-label="Activité documentaire par mois">
                <div class="chart-y"><span>100</span><span>75</span><span>50</span><span>25</span><span>0</span></div>
              <div class="chart-bars" id="assistantUsageChart"></div>
              </div>
              <div class="chart-legend"><span><i></i>Documents traités</span></div>
            </article>
          </div>
        </div>

        <div id="ceoDashboard" class="hidden">
          <div class="page-head">
            <div><small class="eyebrow">VUE D'ENSEMBLE</small><h1>Tableau de bord</h1><p id="ceoGreeting">Bonjour</p><span class="description">Voici un aperçu des documents qui vous sont accessibles.</span></div>
          </div>
          <div class="stats ceo-stats">
            <article class="stat-card"><div><span>Nouveaux documents disponibles</span><strong id="ceoNewCount">0</strong><em>À consulter</em></div></article>
            <article class="stat-card"><div><span>Documents reçus</span><strong id="ceoReceivedCount">0</strong><em>Documents accessibles</em></div></article>
            <article class="stat-card"><div><span>Documents consultés récemment</span><strong id="ceoConsultedCount">0</strong><em>Dernières consultations</em></div></article>
          </div>
          <div class="dashboard-grid ceo-documents-row ceo-documents-vertical">
            <article class="panel recent-panel ceo-recent-panel"><div class="panel-head"><div><h3>Documents consultés récemment</h3><p>Les dernières consultations du CEO</p></div><button class="text-btn" onclick="openPage('documents')">Voir tout</button></div><div id="ceoRecent"></div></article>
            <article class="panel chart-panel ceo-bar-panel">
              <div class="panel-head"><div><h3>Documents consultés par moi</h3><p>Documents consultés par le CEO au fil des mois</p></div></div>
              <div class="bar-chart" id="ceoConsultChart" aria-label="Nombre réel de consultations par mois"></div>
            </article>
          </div>
        </div>

        <div id="adminDashboard" class="hidden">
          <div class="page-head">
            <div><small class="eyebrow">GESTION DE LA PLATEFORME</small><h1>Tableau de bord administrateur</h1><p>Gérez les comptes et accédez aux fonctionnalités documentaires.</p></div>
          </div>
          <div class="stats admin-stats admin-stats-three">
            <article class="stat-card"><div><span>Nombre de comptes</span><strong id="adminAccountCount">0</strong><em>Comptes enregistrés</em></div></article>
            <article class="stat-card"><div><span>Comptes actifs</span><strong id="adminActiveCount">0</strong><em class="positive">Mis à jour automatiquement</em></div></article>
            <article class="stat-card"><div><span>Nouveaux documents disponibles</span><strong id="adminNewDocsCount">0</strong><em class="positive">Documents à consulter</em></div></article>            <article class="stat-card"><div><span>Documents archivés</span><strong id="adminArchivedCount">0</strong><em>Documents classés</em></div></article>
            <article class="stat-card"><div><span>Documents en attente</span><strong id="adminPendingCount">0</strong><em>À traiter</em></div></article>
          </div>
          <div class="dashboard-grid admin-feature-grid">
            <article class="panel chart-panel">
              <div class="panel-head"><div><h3>Documents par catégorie</h3><p>Répartition des documents archivés</p></div></div>
              <div class="pie-chart-area"><div class="pie-chart" id="adminCategoryPie" role="img" aria-label="Documents par catégorie"></div><div class="pie-legend" id="adminCategoryLegend"></div></div>
            </article>
            <article class="panel recent-panel"><div class="panel-head"><div><h3>Documents récents</h3><p>Derniers documents déposés</p></div><button class="text-btn" onclick="openPage('documents')">Voir tout</button></div><div id="adminRecent"></div></article>
          </div>
          <div class="dashboard-grid" style="grid-template-columns:1fr;margin-top:16px">
            <article class="panel chart-panel">
              <div class="panel-head"><div><h3>Présence des utilisateurs</h3><p>Statut de connexion actuel · actualisé automatiquement</p></div><button class="text-btn" type="button" onclick="refreshAdminStatuses()">Actualiser</button></div>
              <div class="admin-chart" aria-label="Fréquence d'utilisation de la plateforme">
                <div class="chart-y"><span>3</span><span>2</span><span>1</span><span>0</span></div>
                <div class="chart-bars" id="adminActivityChart"></div>
              </div>
              <div class="chart-legend"><span><i></i>Utilisateurs en ligne</span></div>
            </article>
          </div>
        </div>
      </div>

      <!-- DOCUMENTS -->
      <div id="documents" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">GESTION DOCUMENTAIRE</small><h1>Documents</h1><p>Consultez tous vos documents plus rapidement</p></div><div class="document-page-actions" data-role="assistante"><input id="exportMonth" type="month" aria-label="Mois à exporter"><button class="secondary" onclick="exportMonthlyDocuments()">Exporter le lot mensuel</button></div></div>
        <div class="document-tools"><div class="document-search"><span>⌕</span><input id="docSearch" placeholder="Rechercher un document, une référence, un tiers..."></div><button class="filter-btn" onclick="toggleFilters()">☷ Filtres</button></div>
        <div id="advancedFilters" class="filters hidden"><select id="type"><option value="">Tous les types</option><option>Bordereau</option><option>Fiche d'intervention</option><option>Note de service</option><option>Note interne</option><option>Courrier</option><option>Contrat</option><option>Facture</option><option>Rapport</option><option>Autre</option></select><select id="status"><option value="">Tous les statuts</option><option>Archivé</option><option>En attente d’identification</option></select><select id="docTier"><option value="">Tous les tiers</option></select><input id="dateFilter" type="date"><button class="secondary" onclick="clearFilters()">Réinitialiser</button></div>
        <article class="panel table-panel"><div class="table-top"><span id="docCount">Affichage de 1 à 20 sur 124 documents</span></div><div class="table-scroll"><table><thead><tr><th>Nom du document</th><th>Type</th><th>Catégorie</th><th>Tiers</th><th>Date d'archivage</th><th>Statut</th><th>Actions</th></tr></thead><tbody id="body"></tbody></table></div><div class="pagination" id="pagination"></div></article>
      </div>

      <!-- DOCUMENTS EN ATTENTE -->
      <div id="pending" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">IDENTIFICATION</small><h1>Documents en attente</h1><p>Sélectionnez un document pour l’identifier et l’archiver.</p></div></div>
        <article class="panel table-panel"><div class="table-top"><span id="pendingCount">Documents en attente d’identification</span></div><div class="table-scroll"><table><thead><tr><th>Nom du document</th><th>Type</th><th>Tiers</th><th>Date du document</th><th>Action</th></tr></thead><tbody id="pendingBody"></tbody></table></div></article>
      </div>

      <!-- DEPOT -->
      <div id="deposit" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">NOUVEAU DOCUMENT</small><h1>Déposer un document</h1><p>Ajoutez les documents à archiver.</p></div></div>
        <div class="deposit-layout">
          <article class="panel upload-panel"><div class="upload-box" id="drop"><div class="upload-icon">⇧</div><h2 id="uploadTitle">Sélectionner des documents</h2><p id="uploadText">Choisissez des fichiers ou glissez-les ici.</p><label class="file-picker-label" for="file">Parcourir les fichiers</label><input id="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple><label class="file-picker-label folder-picker-label" for="folder">Ou choisir un dossier</label><input id="folder" type="file" class="file-picker-input" webkitdirectory directory multiple><small>PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG · 10 Mo maximum par fichier</small></div><div id="selectedFiles" class="selected-files hidden"></div><button id="removeSelectedFiles" class="text-btn hidden" type="button" onclick="clearSelectedFiles()">Tout retirer</button></article>
 <article class="panel form-panel"><h3>Informations du document</h3><label>Référence / objet</label><input id="docRef" required placeholder="Veuillez entrer une référence ou un objet"><label>Type de document</label><div class="type-picker"><select id="dType" onchange="toggleCustomOption('type');toggleDeleteTypeButton()"></select><button id="deleteSelectedType" class="delete-type-button hidden" type="button" onclick="deleteSelectedDocumentType()" title="Supprimer le type sélectionné" aria-label="Supprimer le type sélectionné">⌫</button></div><div id="customTypeBox" class="custom-option hidden"><input id="customType" placeholder="Saisir un nouveau type"><button class="secondary" onclick="run(addDocumentOption('type'))">Ajouter le type</button></div><p class="form-hint">La catégorie est attribuée automatiquement selon le type sélectionné.</p><div id="categoryPreview" class="category-preview">Catégorie : à déterminer</div><label>Date du document</label><div class="date-mode"><select id="dateMode" onchange="toggleDocumentDate()"><option value="day">Date précise</option><option value="year">Année seulement</option></select><input id="docDate" type="date"><input id="docYear" type="number" min="1900" max="2100" class="hidden" placeholder="AAAA"></div><label>Tiers</label><select id="dTier" required><option value="" selected disabled>Veuillez sélectionner un tiers</option></select><label>Destinataire</label><select id="recipient" required><option value="" selected disabled>Veuillez sélectionner un destinataire</option></select><label>Description <span>(optionnel)</span></label><textarea id="description" placeholder="Ajouter une description..."></textarea><button id="depositSubmitButton" class="primary full" onclick="runDeposit()">Déposer et traiter</button></article>
        </div>
      </div>

      <!-- ARCHIVAGE -->
      <div id="archive" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">CONSERVATION</small><h1>Archivage</h1><p>Retrouvez les documents classés par catégorie, mois et année.</p></div></div>
        <div id="archiveCategories" class="archive-categories"></div>
        <div id="archiveFolder" class="panel hidden"><div class="archive-folder-head"><div><button class="back-btn" onclick="closeArchiveFolder()">← Retour</button><h2 id="archiveTitle">Contrats</h2><p>Archives de cette catégorie</p></div></div><div class="archive-search"><span>⌕</span><input id="archiveSearch" placeholder="Rechercher un document, une référence, un tiers..."><button class="filter-btn" onclick="toggleArchiveFilters()">☷ Filtres</button></div><div id="archiveAdvancedFilters" class="filters hidden"><select id="archiveType"><option value="">Tous les types</option></select><select id="archiveTier"><option value="">Tous les tiers</option></select><input id="archiveDate" type="date" aria-label="Filtrer par date"><button class="secondary" onclick="clearArchiveFilters()">Réinitialiser</button></div><div id="archiveDocs"></div></div>
      </div>

      <!-- TIERS -->
      <div id="tiers" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">RELATIONS</small><h1>Tiers</h1><p>Gérez les personnes et organisations liées aux documents.</p></div><button class="primary" onclick="openTierModal()">＋ Ajouter un tiers</button></div>
        <div class="tier-toolbar"><div class="document-search"><span>⌕</span><input id="tierSearch" placeholder="Rechercher un tiers..."></div></div>
        <article class="panel table-panel"><div class="table-scroll"><table><thead><tr><th>Nom / raison sociale</th><th>Type</th><th>Fonction</th><th>Téléphone</th><th>E-mail</th><th>Statut</th><th>Actions</th></tr></thead><tbody id="tierGrid"></tbody></table></div></article><div class="pagination" id="tierPagination"></div>
      </div>

      <!-- NOTIFICATIONS -->
      <div id="notifications" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">CENTRE D'ALERTES</small><h1>Notifications</h1><p id="notificationSubtitle">Suivez les activités et les alertes de la plateforme.</p></div><button class="secondary" onclick="markNotificationsRead()">Tout marquer comme lu</button></div>
        <article class="panel notification-list" id="notificationList" aria-live="polite"></article>
      </div>

      <!-- PROFIL -->
      <div id="settings" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">PARAMÈTRES</small><h1>Compte</h1><p>Gérez les informations et la sécurité de votre compte.</p></div></div>
        <div class="settings-tabs"><button class="tab active" onclick="showProfileSettings()">Profil</button><button class="tab" onclick="showPasswordSettings()">Sécurité</button></div>
        <div id="profileTab" class="settings-tab-content">
          <article class="panel profile-card profile-card-compact">
            <div class="profile-avatar" id="profileAvatar">LM</div>
            <div class="profile-main">
              <h2 id="profileName">MBINA MOUSSAVOU Jeannie Loïca</h2>
              <div class="profile-role-line"><span id="profileRoleBadge" class="role-badge">Assistante de direction</span><button id="profilePresence" class="presence-control" type="button" onclick="toggleProfilePresence()" title="Changer le statut de présence"><i class="presence-dot"></i><span class="presence-label">En ligne</span></button></div>
              <button class="secondary profile-edit-button" onclick="openProfileEdit()">Modifier les informations</button>
            </div>
          </article>
          <article class="panel profile-information"><h3>Informations du profil</h3><div class="profile-fields"><div><small>Nom</small><b id="profileLastName">—</b></div><div><small>Prénom</small><b id="profileFirstName">—</b></div><div><small>Adresse e-mail</small><b id="profileEmail">—</b></div><div><small>Téléphone</small><b id="profilePhone">—</b></div><div><small>Fonction</small><b id="profileFunction">—</b></div><div><small>Adresse</small><b id="profileAddress">—</b></div><div><small>Nationalité</small><b id="profileNationality">—</b></div><div><small>Rôle</small><b id="profileRoleText">—</b></div></div></article>
        </div>
        <div id="securityTab" class="settings-tab-content hidden">
          <article class="panel security-panel">
            <div class="panel-head"><div><h3>Modifier le mot de passe</h3><p>Sécurisez votre compte IT-Docs.</p></div></div>
            <label>Ancien mot de passe</label><div class="password-field"><input id="oldPassword" type="password" placeholder="Votre mot de passe actuel"><button type="button" class="password-toggle" onclick="togglePassword('oldPassword',this)">Afficher</button></div>
            <label>Nouveau mot de passe</label><div class="password-field"><input id="newPassword" type="password" placeholder="Nouveau mot de passe"><button type="button" class="password-toggle" onclick="togglePassword('newPassword',this)">Afficher</button></div>
            <label>Confirmer le nouveau mot de passe</label><div class="password-field"><input id="confirmPassword" type="password" placeholder="Confirmer le nouveau mot de passe"><button type="button" class="password-toggle" onclick="togglePassword('confirmPassword',this)">Afficher</button></div>
            <button class="primary" onclick="changePassword()">Modifier le mot de passe</button>
          </article>
        </div>
      </div>

      <!-- DETAIL -->
      <div id="admin" class="page hidden">
        <div class="page-head"><div><small class="eyebrow">GESTION DES UTILISATEURS</small><h1>Gestion des utilisateurs</h1><p>Créer les comptes et attribuer les rôles.</p></div></div>
        <article class="panel form-panel admin-form-panel">
          <h3>Créer un utilisateur</h3>
          <div class="admin-form-grid">
            <div><label>Nom</label><input id="adminLastName" placeholder="Nom"></div>
            <div><label>Prénom</label><input id="adminFirstName" placeholder="Prénom"></div>
            <div><label>Adresse e-mail</label><input id="adminEmail" type="email" placeholder="utilisateur@it-corp.co"></div><div><label>Num&eacute;ro de t&eacute;l&eacute;phone</label><input id="adminPhone" type="tel" placeholder="Num&eacute;ro de t&eacute;l&eacute;phone"></div><div><label>Adresse physique</label><input id="adminAddress" placeholder="Adresse physique"></div>
            <div><label>Mot de passe initial</label><div class="password-field"><input id="adminPassword" type="password" placeholder="Mot de passe"><button type="button" class="password-toggle" onclick="togglePassword('adminPassword',this)">Afficher</button></div></div>
            <div><label>Rôle</label><select id="adminRole"><option value="assistante">Assistante de direction</option><option value="ceo">CEO</option></select></div>
          </div>
          <button id="createUserButton" class="primary" onclick="createAdminUser()">Créer le compte</button>
        </article>
        <article class="panel" style="margin-top:18px;padding:0;overflow:hidden">
          <div class="panel-head"><h3>Comptes utilisateurs</h3></div>
          <div class="table-scroll"><table><thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead><tbody id="adminUsersTable"></tbody></table></div>
        </article>
      </div>

      <div id="detail" class="page hidden"><button class="back-btn" onclick="openPage('documents')">← Retour aux documents</button><div id="detailBox" class="detail-layout"></div></div>
    </div>
  </main>
</section>


<div id="forgotPasswordModal" class="modal hidden"><div class="modal-card">
  <button class="modal-close" onclick="closeModal('forgotPasswordModal')">×</button>
  <h2>Réinitialiser le mot de passe</h2>
  <p class="subtext">Saisissez l’adresse e-mail associée à votre compte. Vous recevrez un message sécurisé pour choisir un nouveau mot de passe.</p>
  <label>Adresse e-mail</label><input id="forgotEmail" type="email" placeholder="exemple@it-corp.co">
  <div id="forgotResetResult" class="reset-link-result" role="status" aria-live="polite"></div>
  <div class="modal-actions"><button class="secondary" onclick="closeModal('forgotPasswordModal')">Annuler</button><button class="primary" onclick="requestPasswordReset()">Envoyer le mail</button></div>
</div></div>

<!-- MODAL MODIFICATION UTILISATEUR -->
<div id="adminUserModal" class="modal hidden"><div class="modal-card">
  <button class="modal-close" onclick="closeModal('adminUserModal')">×</button>
  <h2>Modifier le compte utilisateur</h2>
  <p class="subtext">Modifiez les informations et le statut du compte.</p>
  <input type="hidden" id="editUserEmail">
  <label>Nom</label><input id="editUserLastName" placeholder="Nom">
  <label>Prénom</label><input id="editUserFirstName" placeholder="Prénom">
  <label>Adresse e-mail</label><input id="editUserEmailField" type="email" placeholder="utilisateur@it-corp.co">
  <label>Rôle</label><select id="editUserRole"><option value="assistante">Assistante de direction</option><option value="ceo">CEO</option></select>
  <div class="modal-actions"><button class="secondary" onclick="closeModal('adminUserModal')">Annuler</button><button class="primary" onclick="saveAdminUserEdit()">Enregistrer les modifications</button></div>
</div></div>

<div id="identifyModal" class="modal hidden"><div class="modal-card"><button class="modal-close" onclick="closeModal('identifyModal')">Fermer</button><h2>Identifier le document</h2><p id="identifyDocumentName" class="subtext"></p><input id="identifyDocumentId" type="hidden"><label>Choisir un type existant</label><select id="identifyType"></select><p id="identifyCategory" class="category-preview"></p><label>Catégorie à associer au type (facultatif)</label><input id="identifyCategoryName" list="identifyCategorySuggestions" maxlength="100" placeholder="Choisir une catégorie existante"><datalist id="identifyCategorySuggestions"></datalist><div class="modal-actions"><button class="primary" onclick="identifyDocument()">Identifier et archiver</button></div></div></div>

<!-- MODAL DEPOT -->
<div id="depositModal" class="modal hidden"><div class="modal-card confirmation"><button class="modal-close" onclick="closeModal('depositModal')">×</button><div class="success-icon">✓</div><h2>Document ajouté</h2><p>Les informations du document ont été enregistrées.</p><div id="depositInfo" class="info-table"></div><div class="modal-actions"><button class="primary" onclick="viewLastDocument()">Voir le document</button><button class="secondary" onclick="closeModal('depositModal')">Fermer</button></div></div></div>\n\n<!-- MODAL TIERS -->
<div id="depositErrorModal" class="modal hidden" role="alertdialog" aria-modal="true" aria-labelledby="depositErrorTitle"><div class="modal-card deposit-error-card"><div class="deposit-error-icon" aria-hidden="true">!</div><h2 id="depositErrorTitle">Dépôt refusé</h2><p id="depositErrorMessage" role="alert"></p><div class="modal-actions"><button id="depositErrorOk" class="primary" type="button" onclick="closeModal('depositErrorModal')">OK</button></div></div></div>
<div id="tierModal" class="modal hidden"><div class="modal-card"><button class="modal-close" onclick="closeModal('tierModal')">×</button><h2 id="tierModalTitle">Ajouter un tiers</h2><p class="subtext">Renseignez les informations du tiers.</p><input type="hidden" id="tierId"><label>Nom / raison sociale</label><input id="tierName" placeholder="Ex : IT-CORP"><label>Prénom</label><input id="tierFirst" placeholder="Prénom (si personne physique)"><label>Fonction</label><input id="tierFunction" placeholder="Fonction"><label>Type de tiers</label><select id="tierType"><option>Client</option><option>Fournisseur</option><option>Partenaire</option><option>Prestataire</option><option>Candidat</option></select><label>Adresse</label><input id="tierAddress" placeholder="Adresse"><label>Téléphone</label><input id="tierPhone" placeholder="Téléphone"><label>E-mail</label><input id="tierEmail" type="email" placeholder="E-mail"><div class="modal-actions"><button class="secondary" onclick="closeModal('tierModal')">Annuler</button><button class="primary" onclick="saveTier()">Enregistrer</button></div></div></div>


<!-- MODAL INFORMATIONS PROFIL -->
<div id="profileEditModal" class="modal hidden"><div class="modal-card">
  <button class="modal-close" onclick="closeModal('profileEditModal')">×</button><h2>Modifier les informations</h2>
  <label>Nom</label><input id="profileEditLastName"><label>Prénom</label><input id="profileEditFirstName"><label>Téléphone</label><input id="profileEditPhone"><label>Fonction</label><input id="profileEditFunction"><label>Adresse</label><input id="profileEditAddress"><label>Nationalité</label><input id="profileEditNationality"><label>Photo de profil</label><input id="profileEditPhoto" type="file" accept="image/*"><label class="remove-profile-photo"><input id="removeProfilePhoto" type="checkbox"> Supprimer ma photo actuelle</label>
  <div class="modal-actions"><button class="secondary" onclick="closeModal('profileEditModal')">Annuler</button><button class="primary" onclick="saveProfileEdit()">Enregistrer</button></div>
</div></div>

<div id="confirmModal" class="modal hidden"><div class="modal-card"><h2 id="confirmTitle">Confirmer l’action</h2><p id="confirmMessage"></p><div class="modal-actions"><button class="secondary" onclick="resolveConfirm(false)">Annuler</button><button class="danger-button" onclick="resolveConfirm(true)">Supprimer</button></div></div></div>
<div id="documentViewer" class="modal document-viewer hidden"><div class="viewer-card"><button class="viewer-back" onclick="closeDocumentViewer()">← Retour à l’aperçu</button><button class="modal-close" onclick="closeDocumentViewer()">×</button><div id="viewerContent"></div></div></div>

<div id="toast" class="toast"></div>
<script src="{{ asset('js/app.js') }}?v=20261019-2"></script>
</body>
</html>
