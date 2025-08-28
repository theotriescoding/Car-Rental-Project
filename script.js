// Περιμένουμε να φορτώσει πρώτα το DOM
document.addEventListener("DOMContentLoaded", function () {

    // --- Login Validation ---
    let loginForm = document.getElementById("loginForm");
    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            let email = document.getElementById("loginEmail").value;
            let password = document.getElementById("loginPassword").value;

            if (email.trim() === "" || password.trim() === "") {
                alert("Συμπλήρωσε όλα τα πεδία!");
                e.preventDefault(); // Σταματάει την αποστολή φόρμας
            }
        });
    }

    // --- Register Validation ---
    let registerForm = document.getElementById("registerForm");
    if (registerForm) {
        registerForm.addEventListener("submit", function (e) {
            let name = document.getElementById("regName").value;
            let email = document.getElementById("regEmail").value;
            let password = document.getElementById("regPassword").value;

            if (name.trim() === "" || email.trim() === "" || password.trim() === "") {
                alert("Όλα τα πεδία είναι υποχρεωτικά!");
                e.preventDefault();
                return;
            }

            // Έλεγχος ότι το email έχει σωστή μορφή
            let emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
            if (!email.match(emailPattern)) {
                alert("Δώσε έγκυρο email!");
                e.preventDefault();
            }

            // Έλεγχος μήκους password
            if (password.length < 6) {
                alert("Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες!");
                e.preventDefault();
            }
        });
    }

    // --- Booking Validation ---
    let bookingForm = document.getElementById("bookingForm");
    if (bookingForm) {
        bookingForm.addEventListener("submit", function (e) {
            let startDateValue = document.getElementById("startDate").value;
            let endDateValue = document.getElementById("endDate").value;

            // Αν δεν έχουν δοθεί ημερομηνίες
            if (!startDateValue || !endDateValue) {
                alert("Συμπλήρωσε και τις δύο ημερομηνίες!");
                e.preventDefault();
                return;
            }

            let startDate = new Date(startDateValue);
            let endDate = new Date(endDateValue);

            if (startDate >= endDate) {
                alert("Η ημερομηνία λήξης πρέπει να είναι μετά την ημερομηνία έναρξης!");
                e.preventDefault();
            }
        });
    }

});
