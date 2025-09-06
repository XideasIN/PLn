import { Link, useNavigate } from 'react-router-dom';

const Header = () => {
  const navigate = useNavigate();

  const handleAnchorNavigation = (anchorId) => {
    // Navigate to home page first
    navigate('/');
    
    // Wait for navigation to complete, then scroll to the anchor
    setTimeout(() => {
      const element = document.getElementById(anchorId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    }, 100);
  };

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <header className="header">
      <nav className="navbar navbar-expand-lg">
        <div className="container">
          <Link className="navbar-brand" to="/" onClick={scrollToTop}>
            <img src="/images/logo.png" alt="logo" />
          </Link>
          <button 
            className="navbar-toggler" 
            type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#navbarText" 
            aria-controls="navbarText" 
            aria-expanded="false" 
            aria-label="Toggle navigation"
          >
            <span className="navbar-toggler-icon"></span>
          </button>
          <div className="collapse navbar-collapse justify-content-between" id="navbarText">
            <ul className="navbar-nav w-100 justify-content-center">
              <li className="nav-item">
                <Link className="nav-link" to="/" onClick={scrollToTop}>Home</Link>
              </li>
              <li className="nav-item">
                <div className="dropdown">
                  <a className="nav-link" href="#services" onClick={() => handleAnchorNavigation('services')}>Service</a>
                </div>
              </li>
              <li className="nav-item">
                <a className="nav-link" href="#how-we-work" onClick={() => handleAnchorNavigation('how-we-work')}>How We Work?</a>
              </li>
              <li className="nav-item">
                <a className="nav-link" href="#about" onClick={() => handleAnchorNavigation('about')}>About Us</a>
              </li>
            </ul>
            <a href="#contact" className="headertop-btn" onClick={() => handleAnchorNavigation('contact')}>Contact us</a>
          </div>
        </div>
      </nav>
    </header>
  );
};

export default Header;