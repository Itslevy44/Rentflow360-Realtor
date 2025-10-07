<?php
// contact.php

require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Set a page title for the header
$page_title = "Contact RentFlow360 - Get in Touch";

// Handle form submission (mock)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real application, you would validate and send the email here.
    $message = '<div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                    <span class="font-medium">Success!</span> Your message has been sent. We will respond shortly.
                </div>';
}

include 'includes/header.php'; 
?>

<main class="min-h-screen bg-gray-50">
    <!-- Contact Page Hero Banner -->
    <div class="bg-gray-800 py-20 mb-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-5xl font-extrabold text-white mb-3">
                Let's Talk Property
            </h1>
            <p class="text-gray-300 text-xl">
                We're here to answer your questions and help you find your next step.
            </p>
        </div>
    </div>

    <!-- Contact Content Section -->
    <div class="max-w-7xl mx-auto px-4 lg:px-6 pb-16">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden lg:grid lg:grid-cols-2">
            
            <!-- Left Column: Contact Form -->
            <div class="p-8 md:p-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 border-b pb-3">Send Us a Message</h2>
                
                <?php echo $message; // Display success message ?>

                <form method="POST" action="contact.php" class="space-y-5">
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" id="name" name="name" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150">
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select id="subject" name="subject" required 
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150 appearance-none">
                            <option value="">Select Inquiry Type</option>
                            <option value="listing">Inquiry about a specific listing</option>
                            <option value="general">General Question</option>
                            <option value="agent">Agent/Seller Query</option>
                            <option value="support">Technical Support</option>
                        </select>
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Your Message</label>
                        <textarea id="message" name="message" rows="4" required 
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 transition duration-150"></textarea>
                    </div>

                    <button type="submit" class="w-full px-6 py-3 font-semibold text-white bg-teal-600 rounded-lg 
                                               shadow-md hover:bg-teal-700 transition duration-300 transform hover:scale-[1.01]">
                        Send Message <i class="fas fa-paper-plane ml-2"></i>
                    </button>
                </form>
            </div>

            <!-- Right Column: Contact Details & Map -->
            <div class="bg-teal-600 p-8 md:p-12 text-white">
                <h2 class="text-3xl font-bold mb-6 border-b border-teal-500 pb-3">Our Information</h2>
                <p class="mb-8 text-teal-100">We are committed to providing reliable service. Feel free to reach out via any of the channels below.</p>
                
                <div class="space-y-6">
                    <!-- Phone -->
                    <div class="flex items-start">
                        <i class="fas fa-phone-alt text-2xl text-teal-300 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-lg">Call Us</p>
                            <p class="text-teal-100">+254 701 234 567</p>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="flex items-start">
                        <i class="fas fa-envelope text-2xl text-teal-300 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-lg">Email Us</p>
                            <p class="text-teal-100">info@rentflow360.com</p>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt text-2xl text-teal-300 mr-4 mt-1"></i>
                        <div>
                            <p class="font-semibold text-lg">Visit Our Office</p>
                            <p class="text-teal-100">RentFlow Towers, 14th Floor, Kilimani, Nairobi, Kenya</p>
                        </div>
                    </div>
                </div>

                <!-- Map Placeholder -->
                <div class="mt-10">
                    <h3 class="font-bold text-xl mb-3">Office Location</h3>
                    <div class="w-full h-48 bg-teal-700 rounded-lg flex items-center justify-center text-teal-300 text-sm italic shadow-inner">
                        <i class="fas fa-map mr-2"></i> [Google Maps Embed Placeholder]
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
