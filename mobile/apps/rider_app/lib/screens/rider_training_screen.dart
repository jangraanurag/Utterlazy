import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RiderTrainingScreen extends ConsumerStatefulWidget {
  const RiderTrainingScreen({super.key});

  @override
  ConsumerState<RiderTrainingScreen> createState() => _RiderTrainingScreenState();
}

class _RiderTrainingScreenState extends ConsumerState<RiderTrainingScreen> {
  List<dynamic> modules = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _fetchModules();
  }

  Future<void> _fetchModules() async {
    setState(() => loading = true);
    try {
      final repo = ref.read(deliveryRepositoryProvider);
      final data = await repo.getTrainingModules();
      modules = data['modules'] as List? ?? [];
    } catch (_) {}
    setState(() => loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Training'),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _fetchModules,
              color: WeloColors.sovereignOrange,
              child: modules.isEmpty
                  ? const Center(child: Text('No training modules available'))
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: modules.length,
                      itemBuilder: (context, index) {
                        final module = modules[index];
                        return _ModuleCard(
                          module: module,
                          onComplete: _fetchModules,
                        );
                      },
                    ),
            ),
    );
  }
}

class _ModuleCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> module;
  final VoidCallback onComplete;

  const _ModuleCard({required this.module, required this.onComplete});

  @override
  ConsumerState<_ModuleCard> createState() => _ModuleCardState();
}

class _ModuleCardState extends ConsumerState<_ModuleCard> {
  bool expanded = false;
  Set<String> completedLessons = {};
  int currentLessonIndex = 0;

  @override
  void initState() {
    super.initState();
    completedLessons = Set<String>.from(
      (widget.module['completed_lessons'] as List?)?.cast<String>() ?? [],
    );
  }

  void _toggleLesson(String lessonId) {
    setState(() {
      if (completedLessons.contains(lessonId)) {
        completedLessons.remove(lessonId);
      } else {
        completedLessons.add(lessonId);
      }
    });
    ref.read(deliveryRepositoryProvider).markLessonComplete(widget.module['id'], lessonId);
  }

  void _startQuiz() {
    final questions = widget.module['quiz_questions'] as List? ?? [];
    if (questions.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No quiz for this module'), backgroundColor: WeloColors.sovereignOrange),
      );
      widget.onComplete();
      return;
    }
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _QuizScreen(
          moduleId: widget.module['id'],
          questions: questions,
          onComplete: widget.onComplete,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lessons = widget.module['lessons'] as List? ?? [];
    final quizPassed = widget.module['quiz_passed'] as bool? ?? false;
    final completed = widget.module['completed'] as bool? ?? false;
    final allLessonsDone = completedLessons.length >= lessons.length && lessons.isNotEmpty;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(
        children: [
          ListTile(
            contentPadding: const EdgeInsets.all(16),
            leading: Icon(
              completed ? Icons.check_circle : quizPassed ? Icons.quiz : Icons.school,
              color: completed ? WeloColors.sovereignOrange : Colors.grey,
              size: 32,
            ),
            title: Text(widget.module['title'], style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text(
              completed ? 'Completed' : '${completedLessons.length}/${lessons.length} lessons',
              style: TextStyle(color: completed ? WeloColors.sovereignOrange : Colors.grey.shade600),
            ),
            trailing: completed
                ? const Icon(Icons.check, color: WeloColors.sovereignOrange)
                : IconButton(
                    icon: Icon(expanded ? Icons.expand_less : Icons.expand_more),
                    onPressed: () => setState(() => expanded = !expanded),
                  ),
            onTap: () => setState(() => expanded = !expanded),
          ),
          if (expanded && !completed) ...[
            ...lessons.asMap().entries.map((entry) {
              final lesson = entry.value;
              final isDone = completedLessons.contains(lesson['id']);
              return CheckboxListTile(
                value: isDone,
                onChanged: (_) => _toggleLesson(lesson['id']),
                title: Text(lesson['title']),
                subtitle: Text(
                  lesson['content'].toString().split('\n').first,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12),
                ),
              );
            }),
            if (allLessonsDone)
              Padding(
                padding: const EdgeInsets.all(16),
                child: ElevatedButton.icon(
                  onPressed: _startQuiz,
                  icon: const Icon(Icons.quiz),
                  label: const Text('Take Quiz'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    minimumSize: const Size(double.infinity, 48),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _QuizScreen extends ConsumerStatefulWidget {
  final String moduleId;
  final List questions;
  final VoidCallback onComplete;

  const _QuizScreen({
    required this.moduleId,
    required this.questions,
    required this.onComplete,
  });

  @override
  ConsumerState<_QuizScreen> createState() => _QuizScreenState();
}

class _QuizScreenState extends ConsumerState<_QuizScreen> {
  List<int?> answers = [];
  bool submitting = false;

  @override
  void initState() {
    super.initState();
    answers = List.filled(widget.questions.length, null);
  }

  Future<void> _submit() async {
    setState(() => submitting = true);
    try {
      final repo = ref.read(deliveryRepositoryProvider);
      final result = await repo.submitQuiz(widget.moduleId, answers.whereType<int>().toList());
      if (mounted) {
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (_) => AlertDialog(
            title: Text(result['passed'] ? 'Passed!' : 'Try Again'),
            content: Text('Score: ${result['score']}% (${result['correct']}/${result['total']})'),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.pop(context);
                  Navigator.pop(context);
                  widget.onComplete();
                },
                child: const Text('OK'),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }
    setState(() => submitting = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Quiz'),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: widget.questions.length + 1,
        itemBuilder: (context, index) {
          if (index == widget.questions.length) {
            return Padding(
              padding: const EdgeInsets.only(top: 16),
              child: ElevatedButton(
                onPressed: answers.any((a) => a == null) || submitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: WeloColors.sovereignOrange,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(double.infinity, 48),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(submitting ? 'Submitting...' : 'Submit Quiz'),
              ),
            );
          }
          final q = widget.questions[index];
          final options = q['options'] as List;
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Q${index + 1}. ${q['question']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 12),
                  ...options.asMap().entries.map((opt) {
                    // ignore: deprecated_member_use
                    return RadioListTile<int>(
                      value: opt.key,
                      groupValue: answers[index],
                      onChanged: (v) => setState(() => answers[index] = v!),
                      title: Text(opt.value.toString()),
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                    );
                  }),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
