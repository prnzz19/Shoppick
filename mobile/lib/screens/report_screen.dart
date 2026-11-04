part of '../main.dart';

class ProductReportScreen extends StatefulWidget {
  final int productId;
  const ProductReportScreen({super.key, required this.productId});
  @override
  State<ProductReportScreen> createState() => _ProductReportScreenState();
}

class _ProductReportScreenState extends State<ProductReportScreen> {
  final description = TextEditingController();
  String reason = 'incorrect_listing';
  List<PlatformFile> evidence = [];
  bool busy = false;
  String? error;
  @override
  void dispose() {
    description.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final files = <http.MultipartFile>[];
      for (var i = 0; i < evidence.length; i++) {
        if (evidence[i].path != null) {
          files.add(await http.MultipartFile.fromPath(
              'evidence[$i]', evidence[i].path!));
        }
      }
      await ApiService().request('products/${widget.productId}/report',
          method: 'POST',
          body: {'reason': reason, 'description': description.text.trim()},
          files: files);
      if (mounted) {
        tell(context, 'Report submitted. Our team will review it.');
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: const Text('Report product')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        const Text('Tell us what needs review.',
            style: TextStyle(
                fontSize: 22, fontWeight: FontWeight.bold, color: navy)),
        const SizedBox(height: 20),
        DropdownButtonFormField<String>(
            initialValue: reason,
            isExpanded: true,
            decoration: const InputDecoration(
                labelText: 'Reason', border: OutlineInputBorder()),
            items: [
              for (final value in [
                'prohibited_product',
                'misleading_information',
                'fake_product',
                'scam_suspicion',
                'inappropriate_product_image',
                'counterfeit_suspicion',
                'incorrect_listing',
                'other'
              ])
                DropdownMenuItem(
                    value: value,
                    child: Text(label(value), overflow: TextOverflow.ellipsis))
            ],
            onChanged:
                busy ? null : (value) => setState(() => reason = value!)),
        const SizedBox(height: 16),
        TextField(
            controller: description,
            maxLines: 5,
            maxLength: 2000,
            decoration: const InputDecoration(
                labelText: 'Description', border: OutlineInputBorder())),
        OutlinedButton.icon(
            onPressed: busy
                ? null
                : () async {
                    final result = await FilePicker.platform.pickFiles(
                        type: FileType.custom,
                        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
                        allowMultiple: true);
                    if (!mounted || result == null) return;
                    if (result.files.length > 3 ||
                        result.files
                            .any((file) => file.size > 2 * 1024 * 1024)) {
                      setState(() => error =
                          'Choose up to 3 images, no larger than 2 MB each.');
                      return;
                    }
                    setState(() {
                      evidence = result.files;
                      error = null;
                    });
                  },
            icon: const Icon(Icons.add_photo_alternate_outlined),
            label: Text('Evidence photos (${evidence.length}/3)')),
        if (error != null)
          Text(error!, style: const TextStyle(color: Colors.red)),
        const SizedBox(height: 16),
        FilledButton(
            onPressed: busy ? null : send,
            child: Text(busy ? 'Submitting...' : 'Submit report')),
      ]));
}
