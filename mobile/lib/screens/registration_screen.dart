part of '../main.dart';

class RegisterScreen extends StatefulWidget {
  final bool profileOnly;
  const RegisterScreen({super.key, this.profileOnly = false});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final form = GlobalKey<FormState>();
  final registration = BuyerRegistrationService();
  final locations = RegistrationLocations();
  final fields = <String, TextEditingController>{
    for (final field in [
      'first_name',
      'last_name',
      'middle_initial',
      'birthday',
      'age',
      'email',
      'phone',
      'address_line',
      'postal_code',
      'password',
      'password_confirmation'
    ])
      field: TextEditingController(),
  };
  Map<String, String> serverErrors = {};
  RegistrationAvailability? availability;
  String? availabilityError, error, sex, idPath, idName, idError;
  DateTime? birthday;
  bool busy = false,
      submitted = false,
      checking = true,
      terms = false,
      showPassword = false,
      showConfirmation = false;
  int checkRevision = 0;

  @override
  void initState() {
    super.initState();
    locations.addListener(refreshLocations);
    locations.loadRegions();
    checkAvailability();
    if (widget.profileOnly) loadProfile();
  }

  void refreshLocations() {
    if (mounted) setState(() {});
  }

  Future<void> checkAvailability() async {
    final revision = ++checkRevision;
    setState(() {
      checking = true;
      availability = null;
      availabilityError = null;
    });
    try {
      final result = await registration.check(refresh: true);
      if (mounted && revision == checkRevision) {
        setState(() => availability = result);
      }
    } catch (e) {
      if (mounted && revision == checkRevision) {
        setState(() => availabilityError = e.toString());
      }
    } finally {
      if (mounted && revision == checkRevision) {
        setState(() => checking = false);
      }
    }
  }

  Future<void> loadProfile() async {
    try {
      final data = await ApiService().request('auth/session');
      if (!mounted) return;
      final user = data['user'] as Map? ?? {};
      setState(() {
        for (final key in [
          'first_name',
          'last_name',
          'middle_initial',
          'email',
          'phone'
        ]) {
          fields[key]!.text = user[key]?.toString() ?? '';
        }
        sex = ['female', 'male'].contains(user['sex']) ? user['sex'] : null;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

  @override
  void dispose() {
    checkRevision++;
    locations.removeListener(refreshLocations);
    locations.dispose();
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  void clearError(String key) {
    if (serverErrors.containsKey(key) || key == 'password') {
      setState(() {
        serverErrors.remove(key);
        if (key == 'password') serverErrors.remove('password_confirmation');
      });
    }
  }

  Future<void> pickBirthday() async {
    final today = BuyerRegistration.today;
    final picked = await showDatePicker(
        context: context,
        initialDate:
            birthday ?? DateTime(today.year - 20, today.month, today.day),
        firstDate: DateTime(1900),
        lastDate: today,
        helpText: 'Select your birthday');
    if (!mounted || picked == null) return;
    setState(() {
      birthday = picked;
      fields['birthday']!.text =
          '${picked.day.toString().padLeft(2, '0')}/${picked.month.toString().padLeft(2, '0')}/${picked.year}';
      fields['age']!.text = '${BuyerRegistration.age(picked)}';
      serverErrors.remove('birthday');
    });
  }

  Future<void> pickId() async {
    try {
      final result = await FilePicker.platform.pickFiles(
          type: FileType.custom,
          allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
          allowMultiple: false,
          withData: false);
      if (!mounted || result == null) return;
      final file = result.files.single;
      final problem = BuyerRegistration.identityFile(file.name, file.size);
      if (problem != null || file.path == null) {
        setState(() {
          idPath = null;
          idName = null;
          idError =
              problem ?? 'Unable to read this file. Please choose it again.';
        });
        return;
      }
      setState(() {
        idPath = file.path;
        idName = file.name;
        idError = null;
        serverErrors.remove('valid_id');
      });
    } catch (_) {
      if (mounted) {
        setState(() => idError = 'Unable to choose your ID. Please try again.');
      }
    }
  }

  Future<void> google() async {
    if (busy ||
        !GoogleAuthService.isSupported ||
        availability?.google != true) {
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final token = await GoogleAuthService().obtainIdToken();
      await ApiService().loginWithGoogle(token);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> submit() async {
    if (busy || checking || availability?.compatible != true) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      error = null;
      submitted = true;
    });
    final valid = form.currentState!.validate();
    setState(() =>
        idError = idPath == null ? 'Upload a valid ID to continue.' : null);
    if (!valid || idPath == null || birthday == null || !locations.complete) {
      return;
    }
    setState(() => busy = true);
    try {
      final values = BuyerRegistration.payload({
        for (final entry in fields.entries)
          if (!['age', 'birthday'].contains(entry.key) &&
              (!widget.profileOnly || !entry.key.startsWith('password')))
            entry.key: entry.value.text,
        'sex': sex!,
      }, locations.payload, birthday!, terms: terms);
      await registration.submit(values, idPath!,
          profileOnly: widget.profileOnly);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          serverErrors = e.fieldErrors
              .map((key, messages) => MapEntry(key, messages.join(' ')));
          error = e.fieldErrors.isEmpty
              ? e.message
              : 'Please check the highlighted fields.';
        });
      }
      if (mounted && serverErrors.isNotEmpty) form.currentState?.validate();
    } catch (_) {
      if (mounted) {
        setState(() =>
            error = 'Unable to upload your registration. Please try again.');
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> policy(String path) async {
    try {
      if (!await launchUrl(ApiConfig.website(path),
              mode: LaunchMode.externalApplication) &&
          mounted) {
        tell(context, 'Unable to open this page.');
      }
    } catch (_) {
      if (mounted) tell(context, 'Unable to open this page.');
    }
  }

  Widget label(String text) => Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Text(text,
          style: const TextStyle(
              fontSize: 13, fontWeight: FontWeight.w600, color: navy)));
  Widget section(String text) => Padding(
      padding: const EdgeInsets.only(top: 26, bottom: 18),
      child: LayoutBuilder(
          builder: (context, constraints) => Row(children: [
                ConstrainedBox(
                    constraints: BoxConstraints(
                        maxWidth: math.max(0, constraints.maxWidth - 28)),
                    child: Text(text,
                        style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.2,
                            color: Color(0xff078f99)))),
                const SizedBox(width: 10),
                const Expanded(child: Divider(color: Color(0xffe8eef2)))
              ])));
  Widget input(String key, String title,
          {String? hint,
          String? helper,
          String? Function(String?)? validator,
          TextInputType? keyboard,
          bool readOnly = false,
          bool obscure = false,
          VoidCallback? onTap,
          Widget? suffix,
          int? maxLength,
          List<String>? autofill}) =>
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        label(title),
        TextFormField(
            key: ValueKey('registration-$key'),
            controller: fields[key],
            enabled: !busy,
            readOnly: readOnly,
            obscureText: obscure,
            onTap: onTap,
            keyboardType: keyboard,
            textInputAction: TextInputAction.next,
            autofillHints: autofill,
            maxLength: maxLength,
            forceErrorText: serverErrors[key],
            validator: validator,
            onChanged: (_) {
              clearError(key);
              if (key == 'password') setState(() {});
            },
            decoration: InputDecoration(
                hintText: hint,
                helperText: helper,
                helperMaxLines: 3,
                suffixIcon: suffix,
                counterText: '',
                fillColor: readOnly && key == 'age'
                    ? const Color(0xfff7f9fb)
                    : Colors.white)),
      ]);
  Widget pair(Widget first, Widget second, {required bool columns}) => columns
      ? Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: first),
          const SizedBox(width: 16),
          Expanded(child: second)
        ])
      : Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [first, const SizedBox(height: 16), second]);
  Widget location(
      String key,
      String title,
      List<PhilippineLocation> items,
      PhilippineLocation? selected,
      FutureOr<void> Function(PhilippineLocation) select,
      {required String hint,
      bool required = true}) {
    final loading = locations.loading == key;
    final enabled = !busy && !loading && items.isNotEmpty;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      label(title),
      FormField<PhilippineLocation>(
          key: ValueKey('registration-$key-${selected?.code ?? 'empty'}'),
          initialValue: selected,
          validator: (_) =>
              serverErrors['${key}_code'] ??
              serverErrors[key] ??
              (required && selected == null
                  ? 'Select a valid ${key == 'city' ? 'city / municipality' : key}.'
                  : null),
          builder: (field) => Semantics(
                button: true,
                enabled: enabled,
                label: title,
                child: InkWell(
                    key: ValueKey('registration-$key'),
                    borderRadius: BorderRadius.circular(10),
                    onTap: !enabled
                        ? null
                        : () async {
                            final choice =
                                await showModalBottomSheet<PhilippineLocation>(
                                    context: context,
                                    isScrollControlled: true,
                                    useSafeArea: true,
                                    showDragHandle: true,
                                    builder: (_) =>
                                        _RegistrationLocationChoices(
                                            title: title,
                                            items: items,
                                            selected: selected));
                            if (mounted && choice != null) {
                              clearError(key);
                              clearError('${key}_code');
                              field.didChange(choice);
                              await select(choice);
                            }
                          },
                    child: InputDecorator(
                        decoration: InputDecoration(
                            enabled: enabled,
                            errorText: field.errorText,
                            suffixIcon: loading
                                ? const Padding(
                                    padding: EdgeInsets.all(15),
                                    child: SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2)))
                                : const Icon(Icons.expand_more),
                            helperText:
                                key == 'province' && locations.noProvince
                                    ? 'This region has no province level.'
                                    : null),
                        child: Text(
                            selected?.name ?? (loading ? 'Loading...' : hint),
                            style: TextStyle(
                                fontSize: 14,
                                color: selected == null
                                    ? const Color(0xff71869e)
                                    : navy)))),
              )),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context).copyWith(
      inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
          hintStyle: const TextStyle(fontSize: 13, color: Color(0xff91a2b7)),
          helperStyle: const TextStyle(fontSize: 11, color: Color(0xff607c9f)),
          errorMaxLines: 4,
          errorStyle: const TextStyle(fontSize: 12),
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: Color(0xffd6e5eb))),
          enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: Color(0xffd6e5eb))),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: teal, width: 1.5))),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              minimumSize: const Size(48, 50),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10)))),
      outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
              minimumSize: const Size(48, 48),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10)),
              side: const BorderSide(color: Color(0xffc7d7e2)))),
    );
    return Theme(
        data: theme,
        child: Scaffold(
            backgroundColor: const Color(0xfff6f9fb),
            resizeToAvoidBottomInset: true,
            appBar: AppBar(
                backgroundColor: const Color(0xfff6f9fb),
                surfaceTintColor: Colors.transparent,
                title: Text(
                    widget.profileOnly
                        ? 'Complete Buyer Profile'
                        : 'Create Buyer Account',
                    style: const TextStyle(fontSize: 16)),
                actions: [
                  if (widget.profileOnly)
                    IconButton(
                        tooltip: 'Sign out',
                        onPressed: busy ? null : () => ApiService().logout(),
                        icon: const Icon(Icons.logout))
                ]),
            body: SafeArea(
                child: SingleChildScrollView(
                    key: const ValueKey('registration-scroll'),
                    keyboardDismissBehavior:
                        ScrollViewKeyboardDismissBehavior.onDrag,
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                    child: Center(
                        child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 760),
                            child: Column(children: [
                              const SizedBox(
                                  width: 220,
                                  height: 52,
                                  child: ShopPickBrand()),
                              const SizedBox(height: 26),
                              Container(
                                  key: const ValueKey('registration-card'),
                                  width: double.infinity,
                                  padding: EdgeInsets.all(
                                      MediaQuery.sizeOf(context).width < 380
                                          ? 18
                                          : 26),
                                  decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(
                                          color: const Color(0xffdbe5ea)),
                                      boxShadow: const [
                                        BoxShadow(
                                            color: Color(0x070f2348),
                                            blurRadius: 6,
                                            offset: Offset(0, 2))
                                      ]),
                                  child: Material(
                                      type: MaterialType.transparency,
                                      child: AutofillGroup(
                                          child: Form(
                                              key: form,
                                              autovalidateMode: submitted
                                                  ? AutovalidateMode
                                                      .onUserInteraction
                                                  : AutovalidateMode.disabled,
                                              child: LayoutBuilder(builder:
                                                  (context, constraints) {
                                                final columns =
                                                    constraints.maxWidth >=
                                                            496 &&
                                                        MediaQuery.textScalerOf(
                                                                    context)
                                                                .scale(14) <=
                                                            20;
                                                return Column(
                                                    crossAxisAlignment:
                                                        CrossAxisAlignment
                                                            .stretch,
                                                    children: [
                                                      const Text(
                                                          'SHOPPICK BUYER',
                                                          style: TextStyle(
                                                              color: Color(
                                                                  0xff078f99),
                                                              fontSize: 11,
                                                              fontWeight:
                                                                  FontWeight
                                                                      .w800,
                                                              letterSpacing:
                                                                  1.4)),
                                                      const SizedBox(height: 8),
                                                      Text(
                                                          widget.profileOnly
                                                              ? 'Complete your SHOPPICK profile'
                                                              : 'Create your SHOPPICK account',
                                                          style:
                                                              const TextStyle(
                                                                  color: navy,
                                                                  fontSize: 24,
                                                                  fontWeight:
                                                                      FontWeight
                                                                          .w800,
                                                                  height: 1.2)),
                                                      const SizedBox(
                                                          height: 10),
                                                      const Text(
                                                          'Join as a Buyer. Verify your email to start shopping. Fields marked * are required.',
                                                          style: TextStyle(
                                                              color: Color(
                                                                  0xff607c9f),
                                                              fontSize: 13,
                                                              height: 1.6)),
                                                      if (!widget
                                                          .profileOnly) ...[
                                                        const SizedBox(
                                                            height: 18),
                                                        OutlinedButton(
                                                            onPressed: !busy &&
                                                                    availability
                                                                            ?.google ==
                                                                        true &&
                                                                    GoogleAuthService
                                                                        .isConfigured
                                                                ? google
                                                                : null,
                                                            child: const Text(
                                                                'Continue with Google')),
                                                        if (availability?.google !=
                                                                true ||
                                                            !GoogleAuthService
                                                                .isConfigured)
                                                          const Padding(
                                                              padding: EdgeInsets
                                                                  .only(top: 8),
                                                              child: Text(
                                                                  'Google registration is currently unavailable.',
                                                                  textAlign:
                                                                      TextAlign
                                                                          .center,
                                                                  style: TextStyle(
                                                                      fontSize:
                                                                          12,
                                                                      color: Color(
                                                                          0xff71869e)))),
                                                      ],
                                                      if (checking)
                                                        const Padding(
                                                            padding:
                                                                EdgeInsets.only(
                                                                    top: 16),
                                                            child:
                                                                LinearProgressIndicator()),
                                                      if (!checking &&
                                                          (availabilityError !=
                                                                  null ||
                                                              availability
                                                                      ?.compatible !=
                                                                  true))
                                                        Container(
                                                            margin: const EdgeInsets
                                                                .only(top: 16),
                                                            padding:
                                                                const EdgeInsets
                                                                    .all(12),
                                                            decoration: BoxDecoration(
                                                                color: const Color(
                                                                    0xfffff7ed),
                                                                borderRadius:
                                                                    BorderRadius
                                                                        .circular(
                                                                            10)),
                                                            child: Column(
                                                                crossAxisAlignment:
                                                                    CrossAxisAlignment
                                                                        .start,
                                                                children: [
                                                                  Text(
                                                                      availabilityError ??
                                                                          'Mobile registration is temporarily unavailable. Please register on the SHOPPICK website.',
                                                                      style: const TextStyle(
                                                                          fontSize:
                                                                              13,
                                                                          color:
                                                                              navy)),
                                                                  Wrap(
                                                                      spacing:
                                                                          8,
                                                                      children: [
                                                                        TextButton(
                                                                            onPressed:
                                                                                checkAvailability,
                                                                            child:
                                                                                const Text('Retry availability')),
                                                                        TextButton(
                                                                            onPressed: () =>
                                                                                policy('/register/buyer'),
                                                                            child: const Text('Register on website'))
                                                                      ]),
                                                                ])),
                                                      section(
                                                          'PERSONAL INFORMATION'),
                                                      pair(
                                                          input('first_name',
                                                              'First Name *',
                                                              validator: (v) =>
                                                                  BuyerRegistration
                                                                      .name(v, 'First name'),
                                                              maxLength: 50,
                                                              autofill: const [
                                                                AutofillHints
                                                                    .givenName
                                                              ]),
                                                          input('last_name',
                                                              'Last Name *',
                                                              validator: (v) =>
                                                                  BuyerRegistration.name(
                                                                      v,
                                                                      'Last name'),
                                                              maxLength: 50,
                                                              autofill: const [
                                                                AutofillHints
                                                                    .familyName
                                                              ]),
                                                          columns: columns),
                                                      const SizedBox(
                                                          height: 16),
                                                      pair(
                                                          input(
                                                              'middle_initial',
                                                              'Middle Initial (optional)',
                                                              maxLength: 5),
                                                          Column(
                                                              crossAxisAlignment:
                                                                  CrossAxisAlignment
                                                                      .start,
                                                              children: [
                                                                label('Sex *'),
                                                                DropdownButtonFormField<
                                                                        String>(
                                                                    key: ValueKey(
                                                                        'registration-sex-${sex ?? 'empty'}'),
                                                                    initialValue:
                                                                        sex,
                                                                    isExpanded:
                                                                        true,
                                                                    hint: const Text(
                                                                        'Select',
                                                                        style: TextStyle(
                                                                            fontSize:
                                                                                13)),
                                                                    decoration: InputDecoration(
                                                                        errorText:
                                                                            serverErrors[
                                                                                'sex']),
                                                                    items: const [
                                                                      DropdownMenuItem(
                                                                          value:
                                                                              'female',
                                                                          child:
                                                                              Text('Female')),
                                                                      DropdownMenuItem(
                                                                          value:
                                                                              'male',
                                                                          child:
                                                                              Text('Male'))
                                                                    ],
                                                                    validator: (v) => v ==
                                                                            null
                                                                        ? 'Select your sex.'
                                                                        : null,
                                                                    onChanged: busy
                                                                        ? null
                                                                        : (value) => setState(() {
                                                                              sex = value;
                                                                              serverErrors.remove('sex');
                                                                            }))
                                                              ]),
                                                          columns: columns),
                                                      const SizedBox(
                                                          height: 16),
                                                      pair(
                                                          input('birthday',
                                                              'Birthday *',
                                                              hint:
                                                                  'dd/mm/yyyy',
                                                              readOnly: true,
                                                              onTap: busy
                                                                  ? null
                                                                  : pickBirthday,
                                                              suffix: IconButton(
                                                                  tooltip:
                                                                      'Select birthday',
                                                                  onPressed: busy
                                                                      ? null
                                                                      : pickBirthday,
                                                                  icon: const Icon(
                                                                      Icons
                                                                          .calendar_today_outlined,
                                                                      size:
                                                                          18)),
                                                              validator: (_) => birthday ==
                                                                      null
                                                                  ? 'Birthday is required.'
                                                                  : (BuyerRegistration.age(birthday) ??
                                                                              -1) <
                                                                          18
                                                                      ? 'You must be at least 18 years old.'
                                                                      : null),
                                                          input('age', 'Age',
                                                              hint:
                                                                  'Auto-calculated',
                                                              readOnly: true,
                                                              helper:
                                                                  'Automatically calculated from Birthday.'),
                                                          columns: columns),
                                                      const Padding(
                                                          padding:
                                                              EdgeInsets.only(
                                                                  top: 10),
                                                          child: Text(
                                                              'You must be at least 18 years old.',
                                                              style: TextStyle(
                                                                  fontSize: 11,
                                                                  color: Color(
                                                                      0xff607c9f)))),
                                                      section(
                                                          'CONTACT INFORMATION'),
                                                      pair(
                                                          input('email',
                                                              'Email *',
                                                              validator:
                                                                  BuyerRegistration
                                                                      .email,
                                                              keyboard: TextInputType.emailAddress,
                                                              readOnly: widget.profileOnly,
                                                              maxLength: 255,
                                                              autofill: const [
                                                                AutofillHints
                                                                    .email
                                                              ]),
                                                          input('phone',
                                                              'Mobile Number *',
                                                              hint:
                                                                  '09XXXXXXXXX or +639XXXXXXXXX',
                                                              validator: BuyerRegistration.mobile,
                                                              keyboard: TextInputType.phone,
                                                              autofill: const [
                                                                AutofillHints
                                                                    .telephoneNumber
                                                              ]),
                                                          columns: columns),
                                                      section(
                                                          'YOUR DELIVERY ADDRESS'),
                                                      input('address_line',
                                                          'House No. / Street *',
                                                          maxLength: 255,
                                                          validator: (v) => (v ?? '').trim().isEmpty ? 'Address is required.' : null,
                                                          autofill: const [
                                                            AutofillHints
                                                                .streetAddressLine1
                                                          ]),
                                                      const SizedBox(
                                                          height: 16),
                                                      pair(
                                                          location(
                                                              'region',
                                                              'Region *',
                                                              locations.regions,
                                                              locations.region,
                                                              locations
                                                                  .selectRegion,
                                                              hint: locations
                                                                          .regions
                                                                          .isEmpty &&
                                                                      locations
                                                                              .loading ==
                                                                          null
                                                                  ? 'No regions available'
                                                                  : 'Select region'),
                                                          location(
                                                              'province',
                                                              locations
                                                                      .noProvince
                                                                  ? 'Province'
                                                                  : 'Province *',
                                                              locations
                                                                  .provinces,
                                                              locations
                                                                  .province,
                                                              locations
                                                                  .selectProvince,
                                                              hint: locations
                                                                      .noProvince
                                                                  ? 'Not applicable'
                                                                  : locations.region ==
                                                                          null
                                                                      ? 'Select region first'
                                                                      : 'Select province',
                                                              required: !locations
                                                                  .noProvince),
                                                          columns: columns),
                                                      const SizedBox(
                                                          height: 16),
                                                      pair(
                                                          location(
                                                              'city',
                                                              'City / Municipality *',
                                                              locations.cities,
                                                              locations.city,
                                                              locations
                                                                  .selectCity,
                                                              hint: locations
                                                                          .region ==
                                                                      null
                                                                  ? 'Select region first'
                                                                  : !locations.noProvince &&
                                                                          locations.province ==
                                                                              null
                                                                      ? 'Select province first'
                                                                      : locations.cities.isEmpty &&
                                                                              locations.loading ==
                                                                                  null
                                                                          ? 'No cities available'
                                                                          : 'Select city / municipality'),
                                                          location(
                                                              'barangay',
                                                              'Barangay *',
                                                              locations
                                                                  .barangays,
                                                              locations
                                                                  .barangay,
                                                              locations
                                                                  .selectBarangay,
                                                              hint: locations
                                                                          .city ==
                                                                      null
                                                                  ? 'Select city / municipality first'
                                                                  : locations.barangays
                                                                              .isEmpty &&
                                                                          locations.loading ==
                                                                              null
                                                                      ? 'No barangays available'
                                                                      : 'Select barangay'),
                                                          columns: columns),
                                                      if (locations.error !=
                                                          null) ...[
                                                        const SizedBox(
                                                            height: 8),
                                                        Text(locations.error!,
                                                            style:
                                                                const TextStyle(
                                                                    color: Colors
                                                                        .red,
                                                                    fontSize:
                                                                        12)),
                                                        Align(
                                                            alignment: Alignment
                                                                .centerLeft,
                                                            child: TextButton(
                                                                onPressed: busy
                                                                    ? null
                                                                    : locations
                                                                        .retry,
                                                                child: const Text(
                                                                    'Retry locations')))
                                                      ],
                                                      const SizedBox(
                                                          height: 16),
                                                      input('postal_code',
                                                          'Postal Code *',
                                                          maxLength: 4,
                                                          keyboard:
                                                              TextInputType
                                                                  .number,
                                                          helper:
                                                              'Enter the four-digit postal code for your address.',
                                                          validator: (v) => RegExp(
                                                                      r'^[0-9]{4}$')
                                                                  .hasMatch(
                                                                      v ?? '')
                                                              ? null
                                                              : 'Postal code must contain exactly 4 digits.'),
                                                      section(
                                                          'ID VERIFICATION'),
                                                      label(
                                                          'Upload Valid ID *'),
                                                      OutlinedButton.icon(
                                                          key: const ValueKey(
                                                              'registration-valid-id'),
                                                          onPressed: busy
                                                              ? null
                                                              : pickId,
                                                          icon: const Icon(
                                                              Icons
                                                                  .upload_file_outlined,
                                                              size: 20),
                                                          label: Text(idName ==
                                                                  null
                                                              ? 'Choose image or PDF'
                                                              : 'Replace selected ID')),
                                                      if (idName != null)
                                                        Padding(
                                                            padding:
                                                                const EdgeInsets
                                                                    .only(
                                                                    top: 8),
                                                            child: Text(idName!,
                                                                style: const TextStyle(
                                                                    fontSize:
                                                                        13,
                                                                    color:
                                                                        navy))),
                                                      const Padding(
                                                          padding:
                                                              EdgeInsets.only(
                                                                  top: 8),
                                                          child: Text(
                                                              'JPG, JPEG, PNG, or PDF, up to 5 MB. Your ID is stored privately. Buyer activation requires email verification.',
                                                              style: TextStyle(
                                                                  fontSize: 12,
                                                                  color: Color(
                                                                      0xff607c9f),
                                                                  height:
                                                                      1.5))),
                                                      if (idError != null ||
                                                          serverErrors[
                                                                  'valid_id'] !=
                                                              null)
                                                        Padding(
                                                            padding: const EdgeInsets
                                                                .only(top: 8),
                                                            child: Text(
                                                                serverErrors[
                                                                        'valid_id'] ??
                                                                    idError!,
                                                                style: const TextStyle(
                                                                    color: Colors
                                                                        .red,
                                                                    fontSize:
                                                                        12))),
                                                      if (!widget
                                                          .profileOnly) ...[
                                                        section('SECURITY'),
                                                        input('password',
                                                            'Password *',
                                                            validator:
                                                                BuyerRegistration
                                                                    .password,
                                                            obscure:
                                                                !showPassword,
                                                            autofill: const [
                                                              AutofillHints
                                                                  .newPassword
                                                            ],
                                                            suffix: TextButton(
                                                                onPressed: busy
                                                                    ? null
                                                                    : () => setState(() =>
                                                                        showPassword =
                                                                            !showPassword),
                                                                child: Text(
                                                                    showPassword
                                                                        ? 'Hide'
                                                                        : 'Show'))),
                                                        const SizedBox(
                                                            height: 16),
                                                        input(
                                                            'password_confirmation',
                                                            'Confirm Password *',
                                                            obscure:
                                                                !showConfirmation,
                                                            validator: (v) => v ==
                                                                        null ||
                                                                    v.isEmpty
                                                                ? 'Confirm your password.'
                                                                : v !=
                                                                        fields['password']!
                                                                            .text
                                                                    ? 'Passwords do not match.'
                                                                    : null,
                                                            suffix: TextButton(
                                                                onPressed: busy
                                                                    ? null
                                                                    : () => setState(() =>
                                                                        showConfirmation =
                                                                            !showConfirmation),
                                                                child: Text(
                                                                    showConfirmation
                                                                        ? 'Hide'
                                                                        : 'Show'))),
                                                        const SizedBox(
                                                            height: 12),
                                                        Wrap(
                                                            spacing: 14,
                                                            runSpacing: 8,
                                                            children: [
                                                              for (final rule
                                                                  in BuyerRegistration.passwordRules(
                                                                          fields['password']!
                                                                              .text)
                                                                      .entries)
                                                                Semantics(
                                                                    label:
                                                                        '${rule.key}: ${rule.value ? 'met' : 'not met'}',
                                                                    child: Row(
                                                                        mainAxisSize:
                                                                            MainAxisSize.min,
                                                                        children: [
                                                                          Icon(
                                                                              rule.value ? Icons.check_circle_outline : Icons.radio_button_unchecked,
                                                                              size: 15,
                                                                              color: rule.value ? const Color(0xff08727a) : const Color(0xff71869e)),
                                                                          const SizedBox(
                                                                              width: 5),
                                                                          Flexible(
                                                                              child: Text(rule.key, style: TextStyle(fontSize: 12, color: rule.value ? const Color(0xff08727a) : const Color(0xff71869e))))
                                                                        ]))
                                                            ]),
                                                      ],
                                                      const SizedBox(
                                                          height: 24),
                                                      FormField<bool>(
                                                          initialValue: terms,
                                                          validator: (_) =>
                                                              serverErrors[
                                                                  'terms'] ??
                                                              (!terms
                                                                  ? 'You must agree to the Terms and Privacy Policy.'
                                                                  : null),
                                                          builder: (field) =>
                                                              Column(
                                                                  crossAxisAlignment:
                                                                      CrossAxisAlignment
                                                                          .start,
                                                                  children: [
                                                                    CheckboxListTile(
                                                                        key: const ValueKey(
                                                                            'registration-terms'),
                                                                        contentPadding:
                                                                            EdgeInsets
                                                                                .zero,
                                                                        controlAffinity:
                                                                            ListTileControlAffinity
                                                                                .leading,
                                                                        value:
                                                                            terms,
                                                                        onChanged:
                                                                            busy
                                                                                ? null
                                                                                : (value) {
                                                                                    setState(() {
                                                                                      terms = value ?? false;
                                                                                      serverErrors.remove('terms');
                                                                                    });
                                                                                    field.didChange(terms);
                                                                                  },
                                                                        title: Wrap(
                                                                            crossAxisAlignment:
                                                                                WrapCrossAlignment.center,
                                                                            children: [
                                                                              const Text('I accept the ', style: TextStyle(fontSize: 13)),
                                                                              TextButton(onPressed: () => policy('/terms'), child: const Text('Terms')),
                                                                              const Text('and', style: TextStyle(fontSize: 13)),
                                                                              TextButton(onPressed: () => policy('/privacy'), child: const Text('Privacy Policy'))
                                                                            ])),
                                                                    if (field
                                                                            .errorText !=
                                                                        null)
                                                                      Text(
                                                                          field
                                                                              .errorText!,
                                                                          style: const TextStyle(
                                                                              fontSize: 12,
                                                                              color: Colors.red)),
                                                                  ])),
                                                      if (error != null)
                                                        Padding(
                                                            padding:
                                                                const EdgeInsets
                                                                    .only(
                                                                    top: 10,
                                                                    bottom: 12),
                                                            child: Semantics(
                                                                liveRegion:
                                                                    true,
                                                                child: Text(
                                                                    error!,
                                                                    style: const TextStyle(
                                                                        color: Colors
                                                                            .red,
                                                                        fontSize:
                                                                            13)))),
                                                      const SizedBox(
                                                          height: 12),
                                                      FilledButton(
                                                          key: const ValueKey(
                                                              'registration-submit'),
                                                          onPressed: busy ||
                                                                  checking ||
                                                                  availability
                                                                          ?.compatible !=
                                                                      true
                                                              ? null
                                                              : submit,
                                                          child: Text(busy
                                                              ? 'Submitting...'
                                                              : widget.profileOnly
                                                                  ? 'Complete Profile'
                                                                  : 'Register as Buyer')),
                                                      const SizedBox(
                                                          height: 10),
                                                      const Text(
                                                          'We will email a 6-digit verification code after registration.',
                                                          textAlign:
                                                              TextAlign.center,
                                                          style: TextStyle(
                                                              fontSize: 12,
                                                              color: Color(
                                                                  0xff71869e))),
                                                    ]);
                                              }))))),
                              const SizedBox(height: 16),
                              Wrap(
                                  crossAxisAlignment: WrapCrossAlignment.center,
                                  alignment: WrapAlignment.center,
                                  children: [
                                    const Text('Already have an account?',
                                        style: TextStyle(
                                            fontSize: 13, color: navy)),
                                    TextButton(
                                        onPressed: busy
                                            ? null
                                            : () async {
                                                if (widget.profileOnly) {
                                                  await ApiService().logout();
                                                } else if (context.mounted) {
                                                  Navigator.maybePop(context);
                                                }
                                              },
                                        child: const Text('Login')),
                                  ]),
                            ])))))));
  }
}

class _RegistrationLocationChoices extends StatefulWidget {
  final String title;
  final List<PhilippineLocation> items;
  final PhilippineLocation? selected;
  const _RegistrationLocationChoices(
      {required this.title, required this.items, this.selected});
  @override
  State<_RegistrationLocationChoices> createState() =>
      _RegistrationLocationChoicesState();
}

class _RegistrationLocationChoicesState
    extends State<_RegistrationLocationChoices> {
  String query = '';
  @override
  Widget build(BuildContext context) {
    final matches = widget.items
        .where((item) => '${item.name} ${item.code}'
            .toLowerCase()
            .contains(query.toLowerCase()))
        .toList();
    return Padding(
        padding:
            EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SizedBox(
            height: MediaQuery.sizeOf(context).height * .68,
            child: Column(children: [
              Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(children: [
                    Expanded(
                        child: Text(widget.title,
                            style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: navy))),
                    IconButton(
                        tooltip: 'Close choices',
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close))
                  ])),
              Padding(
                  padding: const EdgeInsets.all(16),
                  child: TextField(
                      autofocus: true,
                      onChanged: (value) => setState(() => query = value),
                      decoration: const InputDecoration(
                          labelText: 'Search locations',
                          prefixIcon: Icon(Icons.search),
                          border: OutlineInputBorder()))),
              Expanded(
                  child: matches.isEmpty
                      ? const Center(child: Text('No matching locations.'))
                      : ListView.builder(
                          itemCount: matches.length,
                          itemBuilder: (_, i) {
                            final item = matches[i];
                            return ListTile(
                                title: Text(item.name),
                                subtitle: Text(item.code),
                                selected: widget.selected?.code == item.code,
                                onTap: () => Navigator.pop(context, item));
                          })),
            ])));
  }
}
