import { useEffect } from 'react'
import { Routes, Route, useLocation } from 'react-router-dom'
import AOS from 'aos'
import Header from './components/Header'
import Home from './components/Home'
import FAQ from './components/FAQ'
import PrivacyPolicy from './components/PrivacyPolicy'
import TermsConditions from './components/TermsConditions'
import Footer from './components/Footer'
import './bootstrap.css'
import './bootstrap-icons.css'
import 'aos/dist/aos.css'
import './style.css'
import './responsive.css'
import './App.css'

function App() {
  const location = useLocation();

  useEffect(() => {
    // Initialize AOS animation
    AOS.init({
      duration: 1000,
    });

    // Navbar scroll effect
    const handleScroll = () => {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 100) {
        navbar?.classList.add('scrolled');
      } else {
        navbar?.classList.remove('scrolled');
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Smooth scroll to top when route changes
  useEffect(() => {
    window.scrollTo({
      top: 0,
      left: 0,
      behavior: 'instant'
    });
  }, [location.pathname]);

  return (
    <div className="wrapper">
      <Routes>
        <Route path="/" element={
          <>
            <Header />
            <Home />
            <Footer />
          </>
        } />
        <Route path="/privacy-policy" element={
          <>
            <Header />
            <PrivacyPolicy />
            <Footer />
          </>
        } />
        <Route path="/terms-conditions" element={
          <>
            <Header />
            <TermsConditions />
            <Footer />
          </>
        } />
      </Routes>
    </div>
  )
}

export default App