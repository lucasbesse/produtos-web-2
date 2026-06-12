const userMenuButton = document.getElementById('userMenuButton');
const userPopup = document.getElementById('userPopup');

if (userMenuButton && userPopup) {
    userMenuButton.addEventListener('click', function (event) {
        event.stopPropagation();
        userPopup.classList.toggle('active');
    });

    document.addEventListener('click', function (event) {
        if (!userPopup.contains(event.target) && !userMenuButton.contains(event.target)) {
            userPopup.classList.remove('active');
        }
    });
}