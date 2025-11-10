/**
 * Validation des formulaires pour BANDER-SHOP
 * Ce script contient des fonctions de validation pour les formulaires de paiement et d'inscription
 */

// Validation de carte bancaire
function validateCreditCard(cardNumber) {
    // Supprimer les espaces et tirets
    cardNumber = cardNumber.replace(/\s+/g, '').replace(/-/g, '');
    
    // Vérifie si la carte contient uniquement des chiffres
    if (!/^\d+$/.test(cardNumber)) {
        return { valid: false, message: "Le numéro de carte doit contenir uniquement des chiffres" };
    }
    
    // Vérifie la longueur (la plupart des cartes ont entre 13 et 19 chiffres)
    if (cardNumber.length < 13 || cardNumber.length > 19) {
        return { valid: false, message: "Le numéro de carte doit contenir entre 13 et 19 chiffres" };
    }
    
    // Algorithme de Luhn (vérification de la validité du numéro)
    let sum = 0;
    let doubleUp = false;
    
    // Boucle de droite à gauche
    for (let i = cardNumber.length - 1; i >= 0; i--) {
        let digit = parseInt(cardNumber.charAt(i));
        
        if (doubleUp) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }
        
        sum += digit;
        doubleUp = !doubleUp;
    }
    
    // La somme doit être divisible par 10
    const isValid = (sum % 10) === 0;
    
    if (!isValid) {
        return { valid: false, message: "Le numéro de carte n'est pas valide" };
    }
    
    return { valid: true, message: "Numéro de carte valide" };
}

// Validation de la date d'expiration (format MM/AA)
function validateExpiryDate(expiryDate) {
    // Format MM/AA ou MM/AAAA
    if (!/^\d{2}\/\d{2}(\d{2})?$/.test(expiryDate)) {
        return { valid: false, message: "Format de date incorrect (utilisez MM/AA)" };
    }
    
    const parts = expiryDate.split('/');
    const month = parseInt(parts[0], 10);
    let year = parseInt(parts[1], 10);
    
    // Ajuster l'année selon le format (AA ou AAAA)
    if (year < 100) {
        year += 2000;
    }
    
    // Valider le mois (1-12)
    if (month < 1 || month > 12) {
        return { valid: false, message: "Le mois doit être compris entre 01 et 12" };
    }
    
    // Obtenir la date actuelle
    const now = new Date();
    const currentMonth = now.getMonth() + 1; // Janvier = 0
    const currentYear = now.getFullYear();
    
    // Vérifier si la carte n'est pas expirée
    if (year < currentYear || (year === currentYear && month < currentMonth)) {
        return { valid: false, message: "La carte est expirée" };
    }
    
    return { valid: true, message: "Date d'expiration valide" };
}

// Validation du code CVV
function validateCVV(cvv) {
    // Le CVV doit être composé de 3 ou 4 chiffres
    if (!/^\d{3,4}$/.test(cvv)) {
        return { valid: false, message: "Le CVV doit contenir 3 ou 4 chiffres" };
    }
    
    return { valid: true, message: "CVV valide" };
}

// Validation de l'email
function validateEmail(email) {
    // Expression régulière simple pour la validation d'email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailRegex.test(email)) {
        return { valid: false, message: "L'adresse e-mail n'est pas valide" };
    }
    
    return { valid: true, message: "Adresse e-mail valide" };
}

// Validation du numéro de téléphone (format international)
function validatePhone(phone) {
    // Accepte des formats comme +33612345678 ou 0612345678
    const phoneRegex = /^(\+\d{1,3})?[0-9]{9,15}$/;
    
    if (!phoneRegex.test(phone.replace(/\s+/g, ''))) {
        return { valid: false, message: "Le numéro de téléphone n'est pas valide (format international ou national)" };
    }
    
    return { valid: true, message: "Numéro de téléphone valide" };
}

// Formatter automatiquement la carte de crédit pendant la saisie
function formatCreditCard(input) {
    const value = input.value.replace(/\D/g, '');
    const formattedValue = value.replace(/(\d{4})(?=\d)/g, '$1 ');
    input.value = formattedValue;
}

// Formatter automatiquement la date d'expiration
function formatExpiryDate(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    
    input.value = value;
}

// Ajouter les écouteurs d'événements lorsque le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Validation de la carte de crédit
    const cardInput = document.getElementById('numero_carte');
    if (cardInput) {
        cardInput.addEventListener('input', function() {
            formatCreditCard(this);
        });
        
        cardInput.addEventListener('blur', function() {
            const result = validateCreditCard(this.value);
            const errorElement = document.getElementById('numero_carte-error');
            if (errorElement) {
                if (!result.valid) {
                    this.classList.add('error');
                    errorElement.textContent = result.message;
                } else {
                    this.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Validation de la date d'expiration
    const expiryInput = document.getElementById('expiration');
    if (expiryInput) {
        expiryInput.addEventListener('input', function() {
            formatExpiryDate(this);
        });
        
        expiryInput.addEventListener('blur', function() {
            const result = validateExpiryDate(this.value);
            const errorElement = document.getElementById('expiration-error');
            if (errorElement) {
                if (!result.valid) {
                    this.classList.add('error');
                    errorElement.textContent = result.message;
                } else {
                    this.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Validation du CVV
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('blur', function() {
            const result = validateCVV(this.value);
            const errorElement = document.getElementById('cvv-error');
            if (errorElement) {
                if (!result.valid) {
                    this.classList.add('error');
                    errorElement.textContent = result.message;
                } else {
                    this.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Validation de l'email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const result = validateEmail(this.value);
            const errorElement = document.getElementById('email-error');
            if (errorElement) {
                if (!result.valid) {
                    this.classList.add('error');
                    errorElement.textContent = result.message;
                } else {
                    this.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Validation du téléphone
    const phoneInput = document.getElementById('telephone');
    if (phoneInput) {
        phoneInput.addEventListener('blur', function() {
            const result = validatePhone(this.value);
            const errorElement = document.getElementById('telephone-error');
            if (errorElement) {
                if (!result.valid) {
                    this.classList.add('error');
                    errorElement.textContent = result.message;
                } else {
                    this.classList.remove('error');
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Validation du formulaire de paiement
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(event) {
            let formValid = true;
            let errorMessages = [];
            
            // Valider le numéro de carte
            if (cardInput) {
                const cardResult = validateCreditCard(cardInput.value);
                if (!cardResult.valid) {
                    formValid = false;
                    errorMessages.push(cardResult.message);
                    cardInput.classList.add('error');
                    const errorElement = document.getElementById('numero_carte-error');
                    if (errorElement) errorElement.textContent = cardResult.message;
                }
            }
            
            // Valider la date d'expiration
            if (expiryInput) {
                const expiryResult = validateExpiryDate(expiryInput.value);
                if (!expiryResult.valid) {
                    formValid = false;
                    errorMessages.push(expiryResult.message);
                    expiryInput.classList.add('error');
                    const errorElement = document.getElementById('expiration-error');
                    if (errorElement) errorElement.textContent = expiryResult.message;
                }
            }
            
            // Valider le CVV
            if (cvvInput) {
                const cvvResult = validateCVV(cvvInput.value);
                if (!cvvResult.valid) {
                    formValid = false;
                    errorMessages.push(cvvResult.message);
                    cvvInput.classList.add('error');
                    const errorElement = document.getElementById('cvv-error');
                    if (errorElement) errorElement.textContent = cvvResult.message;
                }
            }
            
            if (!formValid) {
                event.preventDefault();
                
                // Afficher une alerte avec les erreurs
                const errorSummary = document.getElementById('error-summary');
                if (errorSummary) {
                    errorSummary.textContent = errorMessages.join(', ');
                    errorSummary.style.display = 'block';
                }
            }
        });
    }
});