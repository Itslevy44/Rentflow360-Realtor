# Rentflow360-Realtor
This repository contains the source code for the RentFlow360 platform, a comprehensive web application prototype designed to connect users, agents, and administrators across the Kenyan real estate market.
Frontend Core
HTML5, CSS3, JavaScript (Vanilla), Provides structure, custom styling, and client-side interactions.
Backend
PHP,Used for server-side includes, page routing, serving dynamic content, and simulating database interactions
Database
MySQL, for actual listing, user, and analytics storage.
Server
Apache, Local environment (XAMPP) required to execute PHP code.


Setup and Installation Instructions

To run this project locally, you must have a PHP-enabled server environment.
Prerequisites

    Web Server: A local server distribution (e.g., XAMPP, MAMP, Laragon) that includes Apache and PHP (7.4+).

    Code Editor: Any standard text editor (VS Code, Sublime Text, etc.).

Steps to Run

    Clone or Download: Get the project source code.

    Placement: Place the entire rentflow360/ directory into your server's root folder (htdocs/ for XAMPP/MAMP).

    Start Server: Start the Apache module in your local server control panel.

    Access Pages: Navigate to the following URLs in your web browser:

        Homepage: http://localhost/rentflow360/index.php

        Search Page: http://localhost/rentflow360/search.php

        Blog Section: http://localhost/rentflow360/blog.php

        Admin Dashboard: http://localhost/rentflow360/admin/index.php

3. Key Design Decisions
A. Modular and Maintainable Structure (PHP Includes)

    Separation of Concerns: The structure relies heavily on PHP include statements (header.php, footer.php, etc.) to ensure the navigation, styling links, and script dependencies are consistent across all pages. This prevents code repetition and facilitates global updates.

B. Scalable User Role Management

    The architecture is designed to support the four required user roles (Guest, Registered User, Agent/Seller, Admin). Access control for features like listing approval (Admin), listing management (Agent), and saving favorites (Registered User) is implemented at a foundational level, ready for database-backed authentication logic.

C. Design and Responsiveness

    Custom CSS Focus: A critical design choice was to use 100% custom CSS for all styling. This demonstrates a strong fundamental understanding of CSS Grid, Flexbox, and media queries, resulting in semantic class names and a high degree of visual control.

    Mobile-Responsive Design: The layout is fully responsive, adapting seamlessly from large desktop views (e.g., the side-nav Admin Dashboard ) to single-column, touch-friendly mobile layouts (e.g., the Contact Form ) and the Blog page.

    Clean, Professional UI: The interface uses a professional teal and gray color palette suitable for a financial and real estate service, ensuring a clean, intuitive, and visually appealing experience, in line with the UI/UX evaluation criteria.

D. Advanced Search Logic

    The Search feature is designed to accommodate complex filtering by multiple criteria (location, type, price, bedrooms, amenities). The goal, as stated in the requirements, is to implement logic that handles partial or messy user input (e.g., matching "one-bedroom" to "1-bedroom"), ensuring robust and relevant results.
