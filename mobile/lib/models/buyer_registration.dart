/// Lightweight mirrors of WebsiteBuyerRegistrationRequest / BuyerIdentity.
/// Uniqueness and geographic hierarchy always remain server-authoritative.
class BuyerRegistration {
  static DateTime get today {
    final manila = DateTime.now().toUtc().add(const Duration(hours: 8));
    return DateTime(manila.year, manila.month, manila.day);
  }

  static String iso(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  static int? age(DateTime? birthday, {DateTime? on}) {
    final now = on ?? today;
    if (birthday == null ||
        DateTime(birthday.year, birthday.month, birthday.day)
            .isAfter(DateTime(now.year, now.month, now.day))) {
      return null;
    }
    var result = now.year - birthday.year;
    if (now.month < birthday.month ||
        (now.month == birthday.month && now.day < birthday.day)) {
      result--;
    }
    return result;
  }

  static String phone(String value) {
    final trimmed = value.trim();
    return RegExp(r'^09[0-9]{9}$').hasMatch(trimmed)
        ? '+63${trimmed.substring(1)}'
        : trimmed;
  }

  static String? name(String? value, String label) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return '$label is required.';
    if (text.runes.length < 2 ||
        text.runes.length > 50 ||
        !RegExp(r"^[\p{L} .'-]+$", unicode: true).hasMatch(text) ||
        !RegExp(r'\p{L}', unicode: true).hasMatch(text)) {
      return '$label must contain 2–50 valid characters.';
    }
    return null;
  }

  static String? email(String? value) {
    final text = (value ?? '').trim();
    return text.length <= 255 &&
            RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(text)
        ? null
        : 'Enter a valid email address.';
  }

  static String? mobile(String? value) =>
      RegExp(r'^\+639[0-9]{9}$').hasMatch(phone(value ?? ''))
          ? null
          : 'Enter a valid Philippine mobile number.';
  static Map<String, bool> passwordRules(String value) => {
        '8+ characters': value.runes.length >= 8,
        'Uppercase': RegExp(r'\p{Lu}', unicode: true).hasMatch(value),
        'Lowercase': RegExp(r'\p{Ll}', unicode: true).hasMatch(value),
        'Number': RegExp(r'\p{N}', unicode: true).hasMatch(value),
        'Special character':
            RegExp(r'[^\p{L}\p{N}\s]', unicode: true).hasMatch(value),
      };
  static String? password(String? value) => passwordRules(value ?? '')
          .values
          .every((ok) => ok)
      ? null
      : 'Password must contain at least 8 characters, uppercase, lowercase, a number and a special character.';
  static String? identityFile(String filename, int size) {
    final extension = filename.split('.').last.toLowerCase();
    if (!['jpg', 'jpeg', 'png', 'pdf'].contains(extension)) {
      return 'Choose a JPG, JPEG, PNG, or PDF file.';
    }
    if (size <= 0) return 'Choose a readable, non-empty file.';
    if (size > 5 * 1024 * 1024) return 'Valid ID must be no larger than 5 MB.';
    return null;
  }

  static Map<String, String> payload(Map<String, String> fields,
          Map<String, String> address, DateTime birthday,
          {required bool terms}) =>
      {
        for (final entry in fields.entries)
          if (entry.key != 'age' && entry.key != 'birthday')
            entry.key: entry.key.startsWith('password')
                ? entry.value
                : entry.value.trim(),
        ...address,
        'email': (fields['email'] ?? '').trim().toLowerCase(),
        'phone': phone(fields['phone'] ?? ''),
        'birthday': iso(birthday),
        'country': 'PH',
        'terms': terms ? '1' : '0',
      };
}
