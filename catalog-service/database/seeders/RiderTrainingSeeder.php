<?php

namespace Database\Seeders;

use App\Models\RiderTrainingLesson;
use App\Models\RiderTrainingModule;
use App\Models\RiderTrainingQuizQuestion;
use Illuminate\Database\Seeder;

class RiderTrainingSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'title' => 'Getting Started',
                'description' => 'Learn the basics of using the rider app',
                'icon' => 'school',
                'sort_order' => 1,
                'lessons' => [
                    ['title' => 'App Overview', 'content' => 'Welcome to UtterLazy Rider App. This lesson covers the main features: home screen, incoming orders, navigation, and earnings.', 'sort_order' => 1],
                    ['title' => 'Going Online', 'content' => 'To start receiving orders, tap the Go Online button. Make sure your GPS is enabled. You will only receive orders when online.', 'sort_order' => 2],
                    ['title' => 'Accepting Orders', 'content' => 'When an order comes in, you will see a popup with pickup and delivery details. Accept or reject quickly. High acceptance rate improves your ranking.', 'sort_order' => 3],
                ],
                'quiz' => [
                    ['question' => 'What must you do before receiving orders?', 'options' => ['Go offline', 'Go online', 'Nothing', 'Call admin'], 'correct_index' => 1],
                    ['question' => 'What improves your rider ranking?', 'options' => ['Rejecting orders', 'High acceptance rate', 'Low rating', 'Going offline'], 'correct_index' => 1],
                ],
            ],
            [
                'title' => 'Delivery Process',
                'description' => 'Step-by-step guide to completing deliveries',
                'icon' => 'delivery_dining',
                'sort_order' => 2,
                'lessons' => [
                    ['title' => 'Reaching the Restaurant', 'content' => 'Navigate to the restaurant using the in-app map. If you cannot find it, use the Call Restaurant button.', 'sort_order' => 1],
                    ['title' => 'Picking Up the Order', 'content' => 'Verify the order items with the restaurant. Collect the package. Mark pickup in the app.', 'sort_order' => 2],
                    ['title' => 'Delivering to Customer', 'content' => 'Navigate to the customer address. Upon arrival, ask for the OTP. Enter it in the app to confirm delivery.', 'sort_order' => 3],
                    ['title' => 'Handling COD Orders', 'content' => 'For Cash on Delivery orders, collect the exact amount from the customer. The cash will be settled in your next payout.', 'sort_order' => 4],
                ],
                'quiz' => [
                    ['question' => 'How do you confirm delivery to the customer?', 'options' => ['Just leave', 'Enter OTP from customer', 'Call admin', 'Mark in app only'], 'correct_index' => 1],
                    ['question' => 'For COD orders, who collects the cash?', 'options' => ['Restaurant', 'Customer', 'Rider', 'Admin'], 'correct_index' => 2],
                ],
            ],
            [
                'title' => 'Safety & Professionalism',
                'description' => 'Stay safe and professional on the job',
                'icon' => 'safety_check',
                'sort_order' => 3,
                'lessons' => [
                    ['title' => 'Road Safety', 'content' => 'Always wear a helmet. Follow traffic rules. Do not use phone while riding. Pull over to check directions.', 'sort_order' => 1],
                    ['title' => 'Customer Interaction', 'content' => 'Be polite and professional. Greet the customer. Hand over the package carefully. Ask for rating.', 'sort_order' => 2],
                    ['title' => 'Emergency Situations', 'content' => 'If you face any trouble on the road, use the SOS button in the app. Share your live location with family for safety.', 'sort_order' => 3],
                    ['title' => 'Hygiene Standards', 'content' => 'Keep your delivery bag clean. Do not mix food with other items. Wash hands regularly. Wear clean clothes.', 'sort_order' => 4],
                ],
                'quiz' => [
                    ['question' => 'What should you do while riding?', 'options' => ['Use phone', 'Wear helmet and follow rules', 'Speed up', 'Ignore signals'], 'correct_index' => 1],
                    ['question' => 'If in trouble, what button to use?', 'options' => ['Logout', 'SOS', 'Home', 'Earnings'], 'correct_index' => 1],
                ],
            ],
        ];

        foreach ($modules as $modData) {
            $lessons = $modData['lessons'];
            $quiz = $modData['quiz'];
            unset($modData['lessons'], $modData['quiz']);

            $module = RiderTrainingModule::create($modData);

            foreach ($lessons as $lesson) {
                RiderTrainingLesson::create(array_merge($lesson, ['module_id' => $module->id]));
            }

            foreach ($quiz as $q) {
                RiderTrainingQuizQuestion::create(array_merge($q, ['module_id' => $module->id]));
            }
        }
    }
}
