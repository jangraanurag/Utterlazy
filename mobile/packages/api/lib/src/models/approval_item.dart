class ApprovalItem {
  final String id;
  final String type;
  final String title;
  final String submittedBy;
  final String? submittedAt;
  final String approvalStatus;
  final Map<String, dynamic> details;

  ApprovalItem({
    required this.id,
    required this.type,
    required this.title,
    required this.submittedBy,
    this.submittedAt,
    this.approvalStatus = 'pending',
    this.details = const {},
  });

  factory ApprovalItem.fromJson(Map<String, dynamic> json) {
    return ApprovalItem(
      id: json['id'].toString(),
      type: json['type'] ?? 'ad',
      title: json['title'] ?? '',
      submittedBy: json['submitted_by'] ?? json['user']?['name'] ?? '',
      submittedAt: json['submitted_at'] ?? json['created_at'] as String?,
      approvalStatus: json['approval_status'] ?? 'pending',
      details: json['details'] is Map ? Map<String, dynamic>.from(json['details']) : {},
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'title': title,
      'submitted_by': submittedBy,
      'submitted_at': submittedAt,
      'approval_status': approvalStatus,
      'details': details,
    };
  }

  ApprovalItem copyWith({
    String? id,
    String? type,
    String? title,
    String? submittedBy,
    String? submittedAt,
    String? approvalStatus,
    Map<String, dynamic>? details,
  }) {
    return ApprovalItem(
      id: id ?? this.id,
      type: type ?? this.type,
      title: title ?? this.title,
      submittedBy: submittedBy ?? this.submittedBy,
      submittedAt: submittedAt ?? this.submittedAt,
      approvalStatus: approvalStatus ?? this.approvalStatus,
      details: details ?? this.details,
    );
  }
}
