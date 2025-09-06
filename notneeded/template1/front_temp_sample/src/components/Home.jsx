import HeroSection from './HeroSection'
import ServicesSection from './ServicesSection'
import HowWeWorkSection from './HowWeWorkSection'
import AboutSection from './AboutSection'
import Testimonials from './Testimonials'
import FaqSection from './FAQ'

const Home = () => {
  return (
    <main className="main">
      <HeroSection />
      <ServicesSection />
      <HowWeWorkSection />
      <FaqSection />
      <AboutSection />
      <Testimonials />
    </main>
  );
};

export default Home;