import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_client.dart';
import '../../widgets/common.dart';

class ProofScreen extends StatefulWidget {
  final ApiClient api;
  final int deliveryId;
  const ProofScreen({required this.api, required this.deliveryId, super.key});
  @override
  State<ProofScreen> createState() => _ProofScreenState();
}

class _ProofScreenState extends State<ProofScreen> {
  final recipient = TextEditingController();
  final notes = TextEditingController();
  final form = GlobalKey<FormState>();
  final picker = ImagePicker();
  XFile? photo;
  bool busy = false;
  @override
  void dispose() {
    recipient.dispose();
    notes.dispose();
    super.dispose();
  }

  Future<void> choose(ImageSource source) async {
    setState(() => busy = true);
    try {
      final result = await picker.pickImage(
          source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
      if (mounted && result != null) setState(() => photo = result);
    } catch (_) {
      if (mounted) {
        showError(
            context,
            const ApiFailure(
                'Unable to open photos or camera. Check device permissions.'));
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> submit() async {
    if (busy || !form.currentState!.validate()) return;
    if (photo == null) {
      showError(context, const ApiFailure('Choose a delivery photo.'));
      return;
    }
    if (!await confirm(context, 'Submit proof of delivery?',
        message:
            'Confirm the recipient and photo. You can then mark the parcel delivered after any required COD collection.')) {
      return;
    }
    setState(() => busy = true);
    try {
      await widget.api
          .upload(widget.deliveryId, photo!, recipient.text, notes.text);
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Proof of delivery')),
      body: Form(
          key: form,
          child: ListView(padding: const EdgeInsets.all(20), children: [
            const Text(
                'Record who received the parcel and attach a clear delivery photo.'),
            const SizedBox(height: 20),
            TextFormField(
                controller: recipient,
                maxLength: 100,
                decoration: const InputDecoration(labelText: 'Recipient name'),
                validator: (v) => v == null || v.trim().isEmpty
                    ? 'Enter the recipient name.'
                    : null),
            const SizedBox(height: 12),
            TextFormField(
                controller: notes,
                maxLength: 1000,
                maxLines: 3,
                decoration: const InputDecoration(
                    labelText: 'Delivery note (optional)')),
            if (photo != null)
              Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Image.file(File(photo!.path),
                      height: 220, fit: BoxFit.contain)),
            OutlinedButton.icon(
                onPressed: busy ? null : () => choose(ImageSource.camera),
                icon: const Icon(Icons.camera_alt_outlined),
                label: const Text('Take photo')),
            OutlinedButton.icon(
                onPressed: busy ? null : () => choose(ImageSource.gallery),
                icon: const Icon(Icons.photo_library_outlined),
                label: const Text('Choose photo')),
            const SizedBox(height: 20),
            FilledButton(
                onPressed: busy ? null : submit,
                child: Text(busy ? 'Please wait…' : 'Submit proof')),
          ])));
}
