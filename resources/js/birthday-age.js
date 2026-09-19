export function calculateAgeFromBirthday(value, today = new Date()) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const birthday = new Date(year, month - 1, day);
    if (
        birthday.getFullYear() !== year ||
        birthday.getMonth() !== month - 1 ||
        birthday.getDate() !== day ||
        birthday > today
    ) return null;

    let age = today.getFullYear() - year;
    const birthdayHasOccurred = today.getMonth() > month - 1 ||
        (today.getMonth() === month - 1 && today.getDate() >= day);

    if (!birthdayHasOccurred) age--;
    return age;
}

function setupBirthdayAge(root) {
    const birthday = root.querySelector('[data-birthday-input]');
    const age = root.querySelector('[data-age-output]');
    const error = root.querySelector('[data-birthday-error]');
    if (!birthday || !age) return;

    const update = () => {
        const selected = birthday.value;
        const calculated = calculateAgeFromBirthday(selected);
        const isFuture = selected !== '' && new Date(`${selected}T00:00:00`) > new Date();
        age.value = calculated ?? '';
        birthday.setCustomValidity(isFuture ? 'Birthday cannot be in the future.' : '');
        error?.classList.toggle('hidden', !isFuture);
    };

    birthday.addEventListener('input', update);
    birthday.addEventListener('change', update);
    update();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-birthday-age]').forEach(setupBirthdayAge);
});
