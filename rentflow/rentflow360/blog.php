<?php
// blog.php

require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Set a page title for the header
$page_title = "RentFlow360 Blog: Property Trends & Home Tips";

// --- Simulate Database Connection for Blog Posts ---
// In a real application, you would fetch posts here.
$mock_posts = [
    [
        'id' => 1,
        'title' => 'Top 5 Emerging Neighborhoods in Nairobi for 2025',
        'summary' => 'Discover the next big investment hotspots as Nairobi’s urban sprawl continues. We look at infrastructure, amenities, and price growth forecasts.',
        'category' => 'Investment',
        'date' => 'Oct 1, 2025',
        'image_url' => 'https://placehold.co/600x400/34D399/ffffff?text=Nairobi+Investment'
    ],
    [
        'id' => 2,
        'title' => 'The Complete Guide to Buying Land in Kenya',
        'summary' => 'From due diligence to legal fees, navigating the land-buying process can be complex. Follow our step-by-step guide for a safe transaction.',
        'category' => 'Legal',
        'date' => 'Sep 25, 2025',
        'image_url' => 'https://placehold.co/600x400/EF4444/ffffff?text=Land+Guide'
    ],
    [
        'id' => 3,
        'title' => 'Renting vs. Buying: Which Financial Path is Right for You?',
        'summary' => 'We break down the costs, long-term implications, and personal factors to consider when making this major life decision in the Kenyan market.',
        'category' => 'Finance',
        'date' => 'Sep 18, 2025',
        'image_url' => 'https://placehold.co/600x400/3B82F6/ffffff?text=Renting+vs+Buying'
    ],
    [
        'id' => 4,
        'title' => 'Interior Design Trends for Modern Kenyan Homes',
        'summary' => 'Get inspired with the latest looks, from Scandinavian simplicity to vibrant African-inspired decor. Make your house a home.',
        'category' => 'Home Decor',
        'date' => 'Sep 10, 2025',
        'image_url' => 'https://placehold.co/600x400/F59E0B/ffffff?text=Design+Trends'
    ],
];
// --- End DB Simulation ---

// NOTE: You must ensure your header.php links to styles.css
include 'includes/header.php'; 
?>

<main>
    <!-- Blog Hero Banner -->
    <div class="section-hero">
        <div class="container">
            <h1>Property Insights & Tips</h1>
            <p>Stay informed with the latest real estate trends and homeowner advice.</p>
        </div>
    </div>

    <!-- Blog Content Grid -->
    <div class="container" style="padding-bottom: 4rem;">
        <div class="blog-grid">
        
            <!-- Main Blog Posts Column -->
            <div class="main-content">
                <?php foreach ($mock_posts as $post): ?>
                    <!-- Blog Post Card -->
                    <article class="post-card">
                        <!-- Image -->
                        <div class="post-image">
                            <img src="<?php echo $post['image_url']; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                 onerror="this.onerror=null; this.src='https://placehold.co/600x400/10b981/ffffff?text=Blog+Image';">
                        </div>

                        <!-- Content -->
                        <div class="post-content">
                            <div>
                                <span class="post-tag">
                                    <?php echo htmlspecialchars($post['category']); ?>
                                </span>
                                <h2>
                                    <a href="blog-post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                </h2>
                                <p class="post-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo $post['date']; ?>
                                </p>
                                <p class="post-summary">
                                    <?php echo htmlspecialchars($post['summary']); ?>
                                </p>
                            </div>
                            
                            <a href="blog-post.php?id=<?php echo $post['id']; ?>" class="read-more">
                                Read Full Article <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <!-- Pagination Placeholder -->
                <div class="pagination-area">
                    <nav class="pagination-nav">
                        <button class="pagination-btn">Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">Next</button>
                    </nav>
                </div>
            </div>

            <!-- Sidebar Column -->
            <aside class="sidebar">
                <!-- Search Widget -->
                <div class="sidebar-widget">
                    <h3>Search Blog</h3>
                    <form>
                        <input type="text" placeholder="Search articles..." style="margin-bottom: 0.75rem;">
                        <button type="submit" class="btn-primary" style="width: 100%; margin-top: 0.75rem;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>

                <!-- Categories Widget -->
                <div class="sidebar-widget">
                    <h3>Categories</h3>
                    <ul>
                        <li><a href="blog.php?cat=investment">Investment (12)</a></li>
                        <li><a href="blog.php?cat=legal">Legal & Documentation (8)</a></li>
                        <li><a href="blog.php?cat=finance">Financing (5)</a></li>
                        <li><a href="blog.php?cat=decor">Home Decor (15)</a></li>
                    </ul>
                </div>
            </aside>

        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
