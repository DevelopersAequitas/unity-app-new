class VersionCompare {
  const VersionCompare._();

  /// Returns 1 when [a] > [b], -1 when [a] < [b], and 0 when equal.
  static int compare(String a, String b) {
    final aParts = _normalize(a);
    final bParts = _normalize(b);
    final maxLength = aParts.length > bParts.length ? aParts.length : bParts.length;

    for (var i = 0; i < maxLength; i++) {
      final aSegment = i < aParts.length ? aParts[i] : 0;
      final bSegment = i < bParts.length ? bParts[i] : 0;

      if (aSegment > bSegment) {
        return 1;
      }
      if (aSegment < bSegment) {
        return -1;
      }
    }

    return 0;
  }

  static List<int> _normalize(String version) {
    return version
        .trim()
        .split('.')
        .map((segment) => int.tryParse(segment) ?? 0)
        .toList(growable: false);
  }
}
