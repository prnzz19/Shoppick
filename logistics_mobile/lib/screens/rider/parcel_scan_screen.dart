import 'dart:async';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

enum ParcelScanPurpose { sellerPickup, deliveryStart, logisticsHubScan }

/// A scan supplies a code, not a status transition. Laravel validates ownership
/// and the exact parcel code after the rider separately confirms the result.
class ParcelScanScreen extends StatefulWidget {
  final String trackingNumber;
  final ParcelScanPurpose purpose;
  const ParcelScanScreen({
    required this.trackingNumber,
    this.purpose = ParcelScanPurpose.sellerPickup,
    super.key,
  });
  @override
  State<ParcelScanScreen> createState() => _ParcelScanScreenState();
}

class _ParcelScanScreenState extends State<ParcelScanScreen>
    with WidgetsBindingObserver {
  final scanner = MobileScannerController(
      autoStart: false,
      detectionSpeed: DetectionSpeed.noDuplicates,
      formats: const [BarcodeFormat.code128]);
  final manual = TextEditingController();
  bool enabled = false,
      deciding = false,
      starting = false,
      cameraControlBusy = false;
  String? problem;
  bool get isDeliveryStart => widget.purpose == ParcelScanPurpose.deliveryStart;
  bool get isStaffScan => widget.purpose == ParcelScanPurpose.logisticsHubScan;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  Future<void> start() async {
    if (!mounted || starting || scanner.value.isStarting) return;
    starting = true;
    setState(() {
      enabled = true;
      problem = null;
    });
    try {
      await WidgetsBinding.instance.endOfFrame;
      if (!mounted) return;
      await scanner.start();
      if (mounted && scanner.value.isRunning) setState(() => problem = null);
    } catch (error) {
      if (mounted) {
        setState(() => problem =
            'Camera could not start ($error). Check camera permission or enter the printed waybill code.');
      }
    } finally {
      if (mounted) setState(() => starting = false);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // A permission dialog can change lifecycle before the camera is granted.
    if (!scanner.value.hasCameraPermission) return;
    if (state != AppLifecycleState.resumed) {
      unawaited(stopCamera());
    } else if (enabled && !deciding) {
      unawaited(start());
    }
  }

  Future<void> stopCamera() async {
    try {
      await scanner.stop();
    } catch (error) {
      if (mounted) {
        setState(() => problem = 'Could not pause the camera ($error).');
      }
    }
  }

  Future<void> toggleTorch() async {
    if (cameraControlBusy) return;
    setState(() => cameraControlBusy = true);
    try {
      await scanner.toggleTorch();
    } catch (error) {
      if (mounted) {
        setState(() => problem = 'Could not change flashlight state ($error).');
      }
    } finally {
      if (mounted) setState(() => cameraControlBusy = false);
    }
  }

  Future<void> switchCamera() async {
    if (cameraControlBusy) return;
    setState(() => cameraControlBusy = true);
    try {
      await scanner.switchCamera();
    } catch (error) {
      if (mounted) {
        setState(() => problem = 'Could not switch cameras ($error).');
      }
    } finally {
      if (mounted) setState(() => cameraControlBusy = false);
    }
  }

  Future<void> preview(String value) async {
    final code = value.trim();
    if (deciding) return;
    if (code.isEmpty || code.length > 100 || code.contains(RegExp(r'[\r\n]'))) {
      setState(() => problem =
          'Enter the parcel code printed on the waybill, up to 100 characters.');
      return;
    }
    deciding = true;
    try {
      await scanner.stop();
    } catch (error) {
      deciding = false;
      if (mounted) {
        setState(() => problem = 'Could not pause the camera ($error).');
      }
      return;
    }
    if (!mounted) return;
    final accepted = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
                title: const Text('Confirm parcel code'),
                content: SingleChildScrollView(
                    child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                      Text(widget.trackingNumber),
                      const SizedBox(height: 12),
                      SelectableText(code),
                      const SizedBox(height: 12),
                      Text(
                        isStaffScan
                            ? 'Confirm the code physically scanned by authorized Logistics staff after the parcel has been received. SHOPPICK will validate the staff permission, parcel code, and current state.'
                            : isDeliveryStart
                                ? 'Confirm the code from the assigned parcel. SHOPPICK will validate this Rider assignment before starting delivery.'
                                : 'Confirm the code from the parcel you physically collected. SHOPPICK will validate this assignment before updating its status.',
                      )
                    ])),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Scan again')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Submit code'))
                ]));
    if (!mounted) return;
    if (accepted == true) {
      Navigator.pop(context, code);
    } else {
      deciding = false;
      if (enabled) await start();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    manual.dispose();
    unawaited(scanner.dispose());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(
          title: Text(
              isStaffScan ? 'Logistics hub scan' : 'Scan assigned parcel')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Text(widget.trackingNumber,
            style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 12),
        Text(isStaffScan
            ? 'This staff-only scan is available after the authorized Logistics receipt step. The server verifies your permission and parcel state.'
            : isDeliveryStart
                ? 'Scan the accepted delivery assignment before starting delivery. The server checks the physical code and assignment.'
                : 'Scan the assigned parcel at the Seller pickup location. Camera access starts only when you choose Enable camera.'),
        const SizedBox(height: 16),
        if (!enabled) const Text('Scanner status: Camera off'),
        if (!enabled)
          FilledButton.icon(
              onPressed: start,
              icon: const Icon(Icons.camera_alt_outlined),
              label: const Text('Enable camera')),
        if (starting)
          const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: LinearProgressIndicator()),
        if (enabled)
          SizedBox(
              height: 280,
              child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: MobileScanner(
                      controller: scanner,
                      onDetect: (capture) {
                        final values = capture.barcodes
                            .map((b) => b.rawValue)
                            .whereType<String>();
                        if (values.isNotEmpty) unawaited(preview(values.first));
                      },
                      errorBuilder: (context, error) => Center(
                          child: Padding(
                              padding: const EdgeInsets.all(20),
                              child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(Icons.no_photography_outlined,
                                        size: 36),
                                    const SizedBox(height: 12),
                                    const Text(
                                        'Camera permission denied or camera unavailable. Check app permissions, or enter the waybill code below.'),
                                    TextButton(
                                        onPressed: start,
                                        child: const Text('Retry camera'))
                                  ])))))),
        if (enabled)
          ValueListenableBuilder<MobileScannerState>(
              valueListenable: scanner,
              builder: (context, state, _) => Row(children: [
                    Expanded(
                        child: Text(state.isRunning
                            ? 'Camera ready · Code 128'
                            : state.isStarting
                                ? 'Starting camera…'
                                : 'Camera paused')),
                    if (state.torchState != TorchState.unavailable)
                      IconButton(
                          tooltip: state.torchState == TorchState.on
                              ? 'Turn flashlight off'
                              : 'Turn flashlight on',
                          onPressed: cameraControlBusy ? null : toggleTorch,
                          icon: Icon(state.torchState == TorchState.on
                              ? Icons.flash_on
                              : Icons.flash_off)),
                    if ((state.availableCameras ?? 1) > 1)
                      IconButton(
                          tooltip: 'Switch camera',
                          onPressed: cameraControlBusy ? null : switchCamera,
                          icon: const Icon(Icons.cameraswitch_outlined))
                  ])),
        if (problem != null)
          Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(problem!, style: const TextStyle(color: Colors.red)),
                    TextButton(
                        onPressed: starting ? null : start,
                        child: const Text('Retry camera'))
                  ])),
        const SizedBox(height: 20),
        TextField(
            key: const ValueKey('manual-parcel-code'),
            controller: manual,
            maxLength: 100,
            enabled: !deciding,
            decoration: InputDecoration(
                labelText: 'Printed parcel code',
                helperText: isStaffScan
                    ? 'Enter the code read from the parcel at the Logistics hub.'
                    : isDeliveryStart
                        ? 'Enter the code from the assigned delivery parcel.'
                        : 'Enter the printed parcel code from the Seller waybill.')),
        const SizedBox(height: 12),
        OutlinedButton(
            onPressed: deciding ? null : () => preview(manual.text),
            child: const Text('Preview entered code')),
        const SizedBox(height: 16),
        Text(isStaffScan
            ? 'The server validates this as a Logistics staff scan; Rider accounts cannot perform this action.'
            : isDeliveryStart
                ? 'The server validates this Rider assignment before starting delivery.'
                : 'This is Rider pickup confirmation. It does not perform a Sorting Center staff inbound scan.'),
      ]));
}
