/**
 * controle.js
 * Validation côté client:
 * - Inscription (registrationForm)
 * - Connexion (login form: _username, _password)
 */

class FormValidator {
  constructor() {
    this.init();
  }

  init() {
    this.injectStyles();
    this.addValidationAttributes();
    this.setupEventListeners();
  }

  injectStyles() {
    const styles = `
      <style>
        .error {
          border-color: #ef4444 !important;
          box-shadow: 0 0 0 3px rgba(239,68,68,0.10) !important;
        }
        .error-message {
          color: #ef4444;
          font-size: 0.875rem;
          margin-top: 0.35rem;
          display: none;
        }
        .touched:invalid {
          border-color: #ef4444 !important;
          box-shadow: 0 0 0 3px rgba(239,68,68,0.10) !important;
        }
      </style>
    `;
    document.head.insertAdjacentHTML("beforeend", styles);
  }

  addValidationAttributes() {
    // INSCRIPTION
    document.querySelectorAll('input[name="fullName"]').forEach((el) => {
      el.setAttribute("maxlength", "100");
      el.setAttribute("autocomplete", "name");
    });

    document.querySelectorAll('input[name="email"]').forEach((el) => {
      el.setAttribute("maxlength", "180");
      el.setAttribute("autocomplete", "email");
      el.setAttribute("inputmode", "email");
    });

    document.querySelectorAll('input[name="password"]').forEach((el) => {
      el.setAttribute("minlength", "6");
      el.setAttribute("autocomplete", "new-password");
    });

    document.querySelectorAll('select[name="userType"]').forEach((el) => {
      el.setAttribute("required", "required");
    });

    ["rpps", "ssn", "adeli"].forEach((name) => {
      document.querySelectorAll(`input[name="${name}"]`).forEach((el) => {
        el.setAttribute("inputmode", "numeric");
        el.setAttribute("autocomplete", "off");
      });
    });

    document.querySelectorAll('input[name="specialty"]').forEach((el) => {
      el.setAttribute("maxlength", "100");
      el.setAttribute("autocomplete", "off");
    });

    document.querySelectorAll('input[name="birthDate"]').forEach((el) => {
      el.setAttribute("autocomplete", "bday");
    });

    // CONNEXION
    document.querySelectorAll('input[name="_username"]').forEach((el) => {
      el.setAttribute("autocomplete", "email");
      el.setAttribute("inputmode", "email");
      el.setAttribute("maxlength", "180");
    });

    document.querySelectorAll('input[name="_password"]').forEach((el) => {
      el.setAttribute("autocomplete", "current-password");
    });
  }

  setupEventListeners() {
    // Submit global (capture via bubbling normal)
    document.addEventListener("submit", (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;

      // Form INSCRIPTION
      if (form.id === "registrationForm") {
        const ok = this.validateRegisterForm(form);
        if (!ok) {
          e.preventDefault();
          this.showErrors(form);
        }
        return;
      }

      // Form CONNEXION: on identifie via champs _username/_password
      const hasLoginFields =
        form.querySelector('input[name="_username"]') &&
        form.querySelector('input[name="_password"]');

      if (hasLoginFields) {
        const ok = this.validateLoginForm(form);
        if (!ok) {
          e.preventDefault();
          this.showErrors(form);
        }
      }
    });

    // Blur (capture)
    document.addEventListener(
      "blur",
      (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (!el.matches("input, select, textarea")) return;

        el.classList.add("touched");
        this.validateField(el);

        // Si on blur userType, revalider conditionnel
        if (el.name === "userType") {
          const form = el.closest("form");
          if (form && form.id === "registrationForm") {
            this.validateConditionalByUserType(form);
          }
        }
      },
      true
    );

    // Input
    document.addEventListener("input", (e) => {
      const el = e.target;
      if (!(el instanceof HTMLElement)) return;
      if (!el.matches("input, select, textarea")) return;

      this.validateField(el);
    });

    // Change
    document.addEventListener("change", (e) => {
      const el = e.target;
      if (!(el instanceof HTMLElement)) return;
      if (!el.matches("input, select, textarea")) return;

      this.validateField(el);

      if (el.name === "userType") {
        const form = el.closest("form");
        if (form && form.id === "registrationForm") {
          this.validateConditionalByUserType(form);
        }
      }
    });
  }

  // ============================
  // Validators communs
  // ============================

  validateEmailValue(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  validateFullNameField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    const cleaned = value.replace(/[^a-zA-ZÀ-ÿ\s\-]/g, "");
    if (value !== cleaned) field.value = cleaned;
    if (field.value.length > 100) field.value = field.value.substring(0, 100);

    if (!field.value.trim()) {
      this.showFieldError(field, "⚠️ Veuillez entrer votre nom complet");
      return false;
    }
    if (field.value.trim().length < 2) {
      this.showFieldError(field, "📝 Le nom doit contenir au moins 2 caractères");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateEmailField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    if (value.length > 180) field.value = value.substring(0, 180);

    if (!field.value.trim()) {
      this.showFieldError(field, "📧 Veuillez entrer votre email");
      return false;
    }
    if (!this.validateEmailValue(field.value)) {
      this.showFieldError(field, "📧 L'email n'est pas valide");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validatePasswordField(field, minLen = 6) {
    if (!field) return true;

    const value = (field.value ?? "").trim();

    if (!value) {
      this.showFieldError(field, "🔐 Veuillez entrer un mot de passe");
      return false;
    }
    if (value.length < minLen) {
      this.showFieldError(field, `🔐 Le mot de passe doit contenir au moins ${minLen} caractères`);
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  // ============================
  // INSCRIPTION: champs spécifiques
  // ============================

  validateUserTypeField(field) {
    if (!field) return true;

    if (!field.value) {
      this.showFieldError(field, "👤 Veuillez sélectionner un type de compte");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateRppsField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    const cleaned = value.replace(/\D/g, "");
    if (value !== cleaned) field.value = cleaned;
    if (field.value.length > 11) field.value = field.value.substring(0, 11);

    if (!field.value) {
      this.showFieldError(field, "🏥 Veuillez entrer le numéro RPPS");
      return false;
    }
    if (!/^\d{11}$/.test(field.value)) {
      this.showFieldError(field, "🏥 Le numéro RPPS doit contenir exactement 11 chiffres");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateSpecialtyField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    const cleaned = value.replace(/[^a-zA-ZÀ-ÿ\s\-]/g, "");
    if (value !== cleaned) field.value = cleaned;
    if (field.value.length > 100) field.value = field.value.substring(0, 100);

    if (!field.value.trim()) {
      this.showFieldError(field, "🏥 Veuillez entrer la spécialité");
      return false;
    }
    if (!/^[a-zA-ZÀ-ÿ\s\-]+$/.test(field.value)) {
      this.showFieldError(field, "🏥 La spécialité ne peut contenir que des lettres, espaces et tirets");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateAdeliField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    const cleaned = value.replace(/\D/g, "");
    if (value !== cleaned) field.value = cleaned;
    if (field.value.length > 9) field.value = field.value.substring(0, 9);

    if (!field.value) {
      this.showFieldError(field, "🩺 Veuillez entrer le numéro ADELI");
      return false;
    }
    if (!/^\d{9}$/.test(field.value)) {
      this.showFieldError(field, "🩺 Le numéro ADELI doit contenir exactement 9 chiffres");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateBirthDateField(field) {
    if (!field) return true;

    if (!field.value) {
      this.showFieldError(field, "📅 Veuillez entrer votre date de naissance");
      return false;
    }

    const birthDate = new Date(field.value);
    const today = new Date();
    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - 13);

    if (birthDate > today) {
      this.showFieldError(field, "📅 La date de naissance ne peut pas être dans le futur");
      return false;
    }
    if (birthDate > minDate) {
      this.showFieldError(field, "👤 Vous devez avoir au moins 13 ans");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateSsnField(field) {
    if (!field) return true;

    const value = field.value ?? "";
    const cleaned = value.replace(/\D/g, "");
    if (value !== cleaned) field.value = cleaned;
    if (field.value.length > 13) field.value = field.value.substring(0, 13);

    if (!field.value) {
      this.showFieldError(field, "🏥 Veuillez entrer le numéro de sécurité sociale");
      return false;
    }
    if (!/^\d{13}$/.test(field.value)) {
      this.showFieldError(field, "🏥 Le numéro de sécurité sociale doit contenir exactement 13 chiffres");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  clearHiddenFieldErrors(form) {
    const selectors = [
      'input[name="rpps"]',
      'input[name="specialty"]',
      'input[name="adeli"]',
      'input[name="birthDate"]',
      'input[name="ssn"]',
    ];

    selectors.forEach((sel) => {
      const el = form.querySelector(sel);
      if (!el) return;
      const container = el.closest("div");
      const hidden = container ? container.style.display === "none" : false;
      if (hidden) this.clearFieldError(el);
    });
  }

  validateConditionalByUserType(form) {
    this.clearHiddenFieldErrors(form);

    const userType = form.querySelector('select[name="userType"]')?.value || "";

    if (userType === "medecin") {
      const rppsField = form.querySelector('input[name="rpps"]');
      if (rppsField && !this.validateRppsField(rppsField)) {
        rppsField.focus();
        return false;
      }
      
      const specialtyField = form.querySelector('input[name="specialty"]');
      if (specialtyField && !this.validateSpecialtyField(specialtyField)) {
        specialtyField.focus();
        return false;
      }
    }

    if (userType === "aidesoignant") {
      const adeliField = form.querySelector('input[name="adeli"]');
      if (adeliField && !this.validateAdeliField(adeliField)) {
        adeliField.focus();
        return false;
      }
    }

    if (userType === "patient") {
      const birthDateField = form.querySelector('input[name="birthDate"]');
      if (birthDateField && !this.validateBirthDateField(birthDateField)) {
        birthDateField.focus();
        return false;
      }
      
      const ssnField = form.querySelector('input[name="ssn"]');
      if (ssnField && !this.validateSsnField(ssnField)) {
        ssnField.focus();
        return false;
      }
    }

    return true;
  }

  // ============================
  // Validation des formulaires
  // ============================

  validateForm(form) {
    // Déterminer le type de formulaire
    if (form.id === "registrationForm") {
      return this.validateRegisterForm(form);
    }
    
    // Formulaire de connexion
    if (form.querySelector('input[name="_username"]')) {
      return this.validateLoginForm(form);
    }
    
    // Formulaire de profil (modification)
    return this.validateProfileForm(form);
  }

  validateProfileForm(form) {
    // Ordre strict : nom complet d'abord avec focus automatique
    const fullNameField = form.querySelector('input[name="fullName"]');
    if (fullNameField && !this.validateFullNameField(fullNameField)) {
      fullNameField.focus();
      return false;
    }
    
    const emailField = form.querySelector('input[name="email"]');
    if (emailField && !this.validateEmailField(emailField)) {
      emailField.focus();
      return false;
    }
    
    // Mot de passe actuel
    const currentPassField = form.querySelector('input[name="currentPassword"]');
    if (currentPassField && currentPassField.value) {
      if (!this.validateCurrentPasswordField(currentPassField)) {
        currentPassField.focus();
        return false;
      }
    }
    
    // Nouveau mot de passe
    const newPassField = form.querySelector('input[name="newPassword"]');
    if (newPassField && newPassField.value) {
      if (!this.validateNewPasswordField(newPassField)) {
        newPassField.focus();
        return false;
      }
      
      // Confirmation du mot de passe
      const confirmPassField = form.querySelector('input[name="confirmPassword"]');
      if (confirmPassField) {
        if (!this.validateConfirmPasswordField(confirmPassField)) {
          confirmPassField.focus();
          return false;
        }
      }
    }
    
    // Champs spécifiques selon le type d'utilisateur - ordre séquentiel
    const specialtyField = form.querySelector('input[name="specialty"]');
    if (specialtyField && !this.validateSpecialtyField(specialtyField)) {
      specialtyField.focus();
      return false;
    }
    
    const rppsField = form.querySelector('input[name="rpps"]');
    if (rppsField && !this.validateRppsField(rppsField)) {
      rppsField.focus();
      return false;
    }
    
    const birthDateField = form.querySelector('input[name="birthDate"]');
    if (birthDateField && !this.validateBirthDateField(birthDateField)) {
      birthDateField.focus();
      return false;
    }
    
    const ssnField = form.querySelector('input[name="ssn"]');
    if (ssnField && !this.validateSsnField(ssnField)) {
      ssnField.focus();
      return false;
    }
    
    const adeliField = form.querySelector('input[name="adeli"]');
    if (adeliField && !this.validateAdeliField(adeliField)) {
      adeliField.focus();
      return false;
    }

    return true;
  }

  validateRegisterForm(form) {
    // Ordre strict avec focus automatique
    const fullNameField = form.querySelector('input[name="fullName"]');
    if (fullNameField && !this.validateFullNameField(fullNameField)) {
      fullNameField.focus();
      return false;
    }
    
    const emailField = form.querySelector('input[name="email"]');
    if (emailField && !this.validateEmailField(emailField)) {
      emailField.focus();
      return false;
    }
    
    const passwordField = form.querySelector('input[name="password"]');
    if (passwordField && !this.validatePasswordField(passwordField, 6)) {
      passwordField.focus();
      return false;
    }
    
    const userTypeField = form.querySelector('select[name="userType"]');
    if (userTypeField && !this.validateUserTypeField(userTypeField)) {
      userTypeField.focus();
      return false;
    }

    // Champs conditionnels
    if (!this.validateConditionalByUserType(form)) return false;

    return true;
  }

  // ============================
  // CONNEXION
  // ============================

  validateLoginForm(form) {
    const emailField = form.querySelector('input[name="_username"]');
    const passField = form.querySelector('input[name="_password"]');

    if (!emailField) return true;

    // Email login
    const emailValue = (emailField.value ?? "").trim();
    if (!emailValue) {
      this.showFieldError(emailField, "📧 Veuillez entrer votre email");
      return false;
    }
    if (!this.validateEmailValue(emailValue)) {
      this.showFieldError(emailField, "📧 L'email n'est pas valide");
      return false;
    }
    this.clearFieldError(emailField);

    // Password login
    if (!passField) return true;
    const passValue = (passField.value ?? "").trim();
    if (!passValue) {
      this.showFieldError(passField, "🔐 Veuillez entrer votre mot de passe");
      return false;
    }
    this.clearFieldError(passField);

    return true;
  }

  // ============================
  // Méthodes de validation manquantes
  // ============================

  validatePasswordField(field, minLength = 8) {
    if (!field) return true;

    const value = field.value ?? "";
    if (!value.trim()) {
      this.showFieldError(field, "� Veuillez entrer votre mot de passe");
      return false;
    }

    if (value.length < minLength) {
      this.showFieldError(field, `🔐 Le mot de passe doit contenir au moins ${minLength} caractères`);
      return false;
    }

    // Pour les nouveaux mots de passe (minLength = 8), vérifier la force
    if (minLength >= 8 && !this.isStrongPassword(value)) {
      this.showFieldError(field, "� Le mot de passe doit contenir: 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateUserTypeField(field) {
    if (!field) return true;

    if (!field.value) {
      this.showFieldError(field, "� Veuillez sélectionner votre type d'utilisateur");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  validateEmailValue(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  isStrongPassword(password) {
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

    return hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar;
  }

  // ============================
  // Router validation field
  // ============================

  validateField(field) {
    if (!field || !field.name) return true;
    if (field.disabled || field.type === "hidden") return true;

    // Champs inscription
    if (field.name === "fullName") return this.validateFullNameField(field);
    if (field.name === "email") return this.validateEmailField(field);
    if (field.name === "password") return this.validatePasswordField(field, 6);
    if (field.name === "userType") return this.validateUserTypeField(field);
    if (field.name === "rpps") return this.validateRppsField(field);
    if (field.name === "specialty") return this.validateSpecialtyField(field);
    if (field.name === "adeli") return this.validateAdeliField(field);
    if (field.name === "birthDate") return this.validateBirthDateField(field);
    if (field.name === "ssn") return this.validateSsnField(field);

    // Champs connexion
    if (field.name === "_username") {
      if (!field.value.trim()) {
        this.showFieldError(field, "📧 Veuillez entrer votre email");
        return false;
      }
      if (!this.validateEmailValue(field.value.trim())) {
        this.showFieldError(field, "📧 L'email n'est pas valide");
        return false;
      }
      this.clearFieldError(field);
      return true;
    }

    if (field.name === "_password") {
      if (!field.value.trim()) {
        this.showFieldError(field, "🔐 Veuillez entrer votre mot de passe");
        return false;
      }
      this.clearFieldError(field);
      return true;
    }

    // Autres champs: rien
    this.clearFieldError(field);
    return true;
  }

  // ============================
  // Error UI
  // ============================

  showFieldError(field, message) {
    if (!field) return;

    field.classList.add("error");

    let errorElement = field.parentNode?.querySelector(".error-message");
    if (!errorElement) {
      errorElement = document.createElement("div");
      errorElement.className = "error-message";
      field.parentNode.appendChild(errorElement);
    }

    errorElement.textContent = message;
    errorElement.style.display = "block";
  }

  clearFieldError(field) {
    if (!field) return;

    field.classList.remove("error");
    const errorElement = field.parentNode?.querySelector(".error-message");
    if (errorElement) errorElement.style.display = "none";
  }

  showErrors(form) {
    const first = form.querySelector(".error");
    if (first) {
      first.focus();
      first.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }
}

// Init
document.addEventListener("DOMContentLoaded", () => {
  // console.log("controle.js chargé");
  window.__validator = new FormValidator();
});
