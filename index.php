<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mkolo Digital - Instant Airtime, Data, and Digital Solutions</title>
  
  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <script>
    // Custom Tailwind theme configuration
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          },
          colors: {
            primary: {
              50: '#eff6ff', 600: '#4f46e5', 700: '#4338ca',
            },
            slate: {
              50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 600: '#475569',
              700: '#334155', 800: '#1e293b', 900: '#0f172a',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-slate-50 font-sans text-slate-700">

  <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-lg border-b border-slate-200">
    <div class="container mx-auto px-4">
      <div class="flex justify-between items-center h-20">
        <a href="#" class="text-2xl font-bold text-slate-800">Mkolo <span class="text-primary-600">Digital</span></a>
        
        <nav class="hidden md:flex items-center space-x-8">
          <a href="#" class="font-medium hover:text-primary-600 transition-colors">Home</a>
          <a href="#services" class="font-medium hover:text-primary-600 transition-colors">Services</a>
          <a href="#" class="font-medium hover:text-primary-600 transition-colors">About</a>
          <a href="#" class="font-medium hover:text-primary-600 transition-colors">Contact</a>
        </nav>

        <div class="hidden md:flex items-center space-x-4">
            <a href="login.php" class="font-semibold text-primary-600 hover:text-primary-700 transition-colors">Login</a>
            <a href="register.php" class="bg-primary-600 text-white font-semibold px-5 py-2.5 rounded-lg hover:bg-primary-700 transition-colors shadow-sm">Register</a>
        </div>

        <button class="md:hidden text-2xl" id="mobileMenuBtn">
          <i class="fas fa-bars"></i>
        </button>
      </div>
    </div>

    <div id="mobileNav" class="hidden md:hidden absolute top-20 left-0 w-full bg-slate-900 text-white">
        <div class="flex flex-col space-y-1 px-4 pt-2 pb-4">
            <a href="#" class="block py-3 px-4 rounded-md hover:bg-slate-800">Home</a>
            <a href="#services" class="block py-3 px-4 rounded-md hover:bg-slate-800">Services</a>
            <a href="#" class="block py-3 px-4 rounded-md hover:bg-slate-800">About</a>
            <a href="#" class="block py-3 px-4 rounded-md hover:bg-slate-800">Contact</a>
            <div class="border-t border-slate-700 my-2"></div>
            <a href="login.php" class="block py-3 px-4 rounded-md hover:bg-slate-800">Login</a>
            <a href="register.php" class="block py-3 px-4 rounded-md bg-primary-600 hover:bg-primary-700 text-center">Register</a>
        </div>
    </div>
  </header>

  <main>
    <section class="relative bg-gradient-to-br from-slate-900 to-slate-800 text-white">
        <div class="container mx-auto px-4 py-24 md:py-32 text-center">
            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tighter">Your Hub for Digital Solutions</h1>
            <p class="mt-6 max-w-2xl mx-auto text-lg md:text-xl text-slate-300">From instant Airtime & Data top-ups to professional CAC registrations, we provide the tools you need to succeed.</p>
            <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php" class="bg-primary-600 text-white font-semibold px-8 py-3 rounded-lg hover:bg-primary-700 transition-transform hover:scale-105 shadow-lg">
                    <i class="fas fa-user-plus mr-2"></i> Create an Account
                </a>
                <a href="login.php" class="bg-white text-slate-800 font-semibold px-8 py-3 rounded-lg hover:bg-slate-100 transition-transform hover:scale-105 shadow-lg">
                    Access Your Dashboard
                </a>
            </div>
        </div>
    </section>

    <section id="vtu-ads" class="py-20 md:py-28">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="text-sm font-bold uppercase text-primary-600">NEW SERVICES</span>
                    <h2 class="mt-2 text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">
                        Instant Airtime & Data Top-ups
                    </h2>
                    <p class="mt-6 text-lg text-slate-600">
                        Never run out of credit or data again. Our new VTU services are fast, reliable, and available 24/7 for all major networks in Nigeria.
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-bolt text-primary-600 text-xl mt-1"></i>
                            <div class="ml-4">
                                <h4 class="font-semibold text-slate-800">Lightning-Fast Delivery</h4>
                                <p class="text-slate-600">Receive your top-up in seconds after payment.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-tags text-primary-600 text-xl mt-1"></i>
                            <div class="ml-4">
                                <h4 class="font-semibold text-slate-800">Affordable Prices</h4>
                                <p class="text-slate-600">Enjoy competitive pricing on all data plans and airtime.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-broadcast-tower text-primary-600 text-xl mt-1"></i>
                            <div class="ml-4">
                                <h4 class="font-semibold text-slate-800">All Networks Supported</h4>
                                <p class="text-slate-600">Top-up MTN, Glo, Airtel, 9mobile, and more.</p>
                            </div>
                        </li>
                    </ul>
                     <a href="dashboard.php" class="mt-10 inline-block bg-primary-600 text-white font-semibold px-8 py-3 rounded-lg hover:bg-primary-700 transition-transform hover:scale-105 shadow-lg">
                        <i class="fas fa-tachometer-alt mr-2"></i> Go to Dashboard
                    </a>
                </div>
                <div class="flex justify-center">
                    <div class="relative w-72 h-[34rem] bg-slate-800 rounded-[3rem] p-4 shadow-2xl border-4 border-slate-600">
                        <div class="absolute top-2 left-1/2 -translate-x-1/2 w-20 h-5 bg-slate-800 rounded-b-lg"></div>
                        <div class="w-full h-full bg-white rounded-[2rem] p-6 flex flex-col justify-center items-center space-y-8">
                            <h3 class="font-bold text-slate-800 text-xl text-center">Buy Airtime & Data</h3>
                            <div class="w-24 h-24 bg-primary-50 rounded-full flex items-center justify-center">
                                <i class="fas fa-wifi text-primary-600 text-5xl"></i>
                            </div>
                            <div class="w-24 h-24 bg-primary-50 rounded-full flex items-center justify-center">
                                <i class="fas fa-mobile-alt text-primary-600 text-5xl"></i>
                            </div>
                            <p class="text-center text-slate-600">Available now on your dashboard!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="py-20 md:py-28 bg-slate-100">
      <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900">Our Professional Digital Solutions</h2>
          <p class="mt-4 text-lg text-slate-600">We offer a wide range of services to help you establish and grow your online presence.</p>
        </div>
        <div class="mt-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-landmark text-primary-600 text-2xl"></i>
                </div>
                <h3 class="mt-6 font-bold text-slate-800 text-lg">CAC Registration</h3>
                <p class="mt-2 text-slate-600">Fast and reliable business name and company registration services.</p>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-globe text-primary-600 text-2xl"></i>
                </div>
                <h3 class="mt-6 font-bold text-slate-800 text-lg">Web Development</h3>
                <p class="mt-2 text-slate-600">Custom websites tailored to your business needs with modern tech.</p>
            </div>
            
            <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fab fa-whatsapp text-primary-600 text-2xl"></i>
                </div>
                <h3 class="mt-6 font-bold text-slate-800 text-lg">Bot Development</h3>
                <p class="mt-2 text-slate-600">Automate conversations with custom WhatsApp bots for your business.</p>
            </div>
            
            <div class="bg-white p-8 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all">
                <div class="w-14 h-14 bg-primary-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-primary-600 text-2xl"></i>
                </div>
                <h3 class="mt-6 font-bold text-slate-800 text-lg">Digital Marketing</h3>
                <p class="mt-2 text-slate-600">Strategies to increase your online visibility and growth.</p>
            </div>
        </div>
      </div>
    </section>

    <section class="py-20 md:py-28">
      <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto">
          <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900">Why Choose Mkolo Digital?</h2>
          <p class="mt-4 text-lg text-slate-600">We are committed to providing exceptional quality and support.</p>
        </div>
        <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="text-center p-8">
            <div class="w-16 h-16 mx-auto bg-primary-50 rounded-full flex items-center justify-center">
                <i class="fas fa-rocket text-primary-600 text-3xl"></i>
            </div>
            <h3 class="mt-6 text-xl font-bold text-slate-800">Fast & Reliable</h3>
            <p class="mt-2 text-slate-600">Our services are optimized for speed and reliability, ensuring your business runs smoothly.</p>
          </div>
          <div class="text-center p-8">
            <div class="w-16 h-16 mx-auto bg-primary-50 rounded-full flex items-center justify-center">
                <i class="fas fa-lock text-primary-600 text-3xl"></i>
            </div>
            <h3 class="mt-6 text-xl font-bold text-slate-800">Secure Solutions</h3>
            <p class="mt-2 text-slate-600">We implement the latest security measures to protect your data and transactions.</p>
          </div>
          <div class="text-center p-8">
            <div class="w-16 h-16 mx-auto bg-primary-50 rounded-full flex items-center justify-center">
                <i class="fas fa-headset text-primary-600 text-3xl"></i>
            </div>
            <h3 class="mt-6 text-xl font-bold text-slate-800">24/7 Support</h3>
            <p class="mt-2 text-slate-600">Our dedicated support team is available around the clock to assist you with any issues.</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="bg-slate-800 text-slate-300">
    <div class="container mx-auto px-4 pt-16 pb-8">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
        <div class="col-span-2 md:col-span-1">
            <h3 class="text-xl font-bold text-white">Mkolo Digital</h3>
            <p class="mt-4">Providing cutting-edge digital solutions to help businesses thrive in the digital age.</p>
            <div class="mt-6 flex space-x-4">
                <a href="#" class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center hover:bg-primary-600 transition-colors"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center hover:bg-primary-600 transition-colors"><i class="fab fa-twitter"></i></a>
                <a href="#" class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center hover:bg-primary-600 transition-colors"><i class="fab fa-instagram"></i></a>
                <a href="#" class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center hover:bg-primary-600 transition-colors"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        <div>
            <h4 class="font-semibold text-white tracking-wider uppercase">Quick Links</h4>
            <ul class="mt-4 space-y-2">
                <li><a href="#" class="hover:text-white transition-colors">Home</a></li>
                <li><a href="#services" class="hover:text-white transition-colors">Services</a></li>
                <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
                <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-white tracking-wider uppercase">Services</h4>
            <ul class="mt-4 space-y-2">
                <li><a href="#" class="hover:text-white transition-colors">CAC Registration</a></li>
                <li><a href="#" class="hover:text-white transition-colors">Web Development</a></li>
                <li><a href="#" class="hover:text-white transition-colors">Bot Development</a></li>
                <li><a href="#" class="hover:text-white transition-colors">Digital Marketing</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-white tracking-wider uppercase">Contact Us</h4>
             <ul class="mt-4 space-y-2">
                <li class="flex items-start"><i class="fas fa-map-marker-alt mt-1 mr-2"></i> Lagos, Nigeria</li>
                <li class="flex items-start"><i class="fas fa-phone mt-1 mr-2"></i> +234 123 456 7890</li>
                <li class="flex items-start"><i class="fas fa-envelope mt-1 mr-2"></i> support@mkolodigital.com.ng</li>
            </ul>
        </div>
      </div>
      <div class="mt-16 border-t border-slate-700 pt-8 text-center text-slate-400">
        <p>&copy; <?php echo date("Y"); ?> Mkolo Digital. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
      const nav = document.getElementById('mobileNav');
      nav.classList.toggle('hidden');
    });
  </script>
</body>
</html>
