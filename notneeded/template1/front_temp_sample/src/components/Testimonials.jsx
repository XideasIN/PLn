import React, { useState, useEffect } from 'react';
import './Testimonials.css';

const Testimonials = () => {
  const [testimonials, setTestimonials] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [loading, setLoading] = useState(true);

  // Default testimonials in case API fails
  const defaultTestimonials = [
    {
      id: 1,
      name: "Sarah Johnson",
      content: "LoanFlow made getting my business loan incredibly easy. The process was transparent, fast, and the customer service was exceptional. I got approved within 24 hours!",
      rating: 5,
      location: "Toronto, ON",
      loan_type: "Business Loan"
    },
    {
      id: 2,
      name: "Michael Chen",
      content: "I was skeptical about online lending, but LoanFlow exceeded my expectations. The rates were competitive, and the entire process was handled professionally. Highly recommended!",
      rating: 5,
      location: "Vancouver, BC",
      loan_type: "Personal Loan"
    },
    {
      id: 3,
      name: "Emily Rodriguez",
      content: "When I needed emergency funds for home repairs, LoanFlow was there for me. Quick approval, fair terms, and excellent support throughout the process.",
      rating: 5,
      location: "Calgary, AB",
      loan_type: "Home Improvement Loan"
    },
    {
      id: 4,
      name: "David Thompson",
      content: "The team at LoanFlow understood my unique situation and worked with me to find the perfect loan solution. Their personalized approach made all the difference.",
      rating: 5,
      location: "Montreal, QC",
      loan_type: "Personal Loan"
    },
    {
      id: 5,
      name: "Lisa Park",
      content: "From application to funding, everything was seamless. The online platform is user-friendly, and the communication was clear every step of the way.",
      rating: 5,
      location: "Ottawa, ON",
      loan_type: "Business Loan"
    },
    {
      id: 6,
      name: "Robert Wilson",
      content: "I've used other lending services before, but LoanFlow stands out for their transparency and competitive rates. No hidden fees, just honest lending.",
      rating: 5,
      location: "Halifax, NS",
      loan_type: "Auto Loan"
    }
  ];

  useEffect(() => {
    fetchTestimonials();
    const interval = setInterval(() => {
      setCurrentIndex(prev => (prev + 1) % Math.max(testimonials.length, defaultTestimonials.length));
    }, 5000);
    return () => clearInterval(interval);
  }, [testimonials.length]);

  const fetchTestimonials = async () => {
    try {
      const response = await fetch('/api/testimonials');
      if (response.ok) {
        const data = await response.json();
        if (data.testimonials && data.testimonials.length > 0) {
          setTestimonials(data.testimonials);
        } else {
          setTestimonials(defaultTestimonials);
        }
      } else {
        setTestimonials(defaultTestimonials);
      }
    } catch (error) {
      console.log('Using default testimonials');
      setTestimonials(defaultTestimonials);
    } finally {
      setLoading(false);
    }
  };

  const renderStars = (rating) => {
    return Array.from({ length: 5 }, (_, index) => (
      <span key={index} className={`star ${index < rating ? 'filled' : ''}`}>
        ★
      </span>
    ));
  };

  const activeTestimonials = testimonials.length > 0 ? testimonials : defaultTestimonials;
  const visibleTestimonials = activeTestimonials.slice(currentIndex, currentIndex + 3);
  
  // If we don't have enough testimonials to show 3, wrap around
  if (visibleTestimonials.length < 3) {
    const remaining = 3 - visibleTestimonials.length;
    visibleTestimonials.push(...activeTestimonials.slice(0, remaining));
  }

  if (loading) {
    return (
      <section className="testimonials-section">
        <div className="container">
          <div className="loading-testimonials">Loading testimonials...</div>
        </div>
      </section>
    );
  }

  return (
    <section className="testimonials-section">
      <div className="container">
        <div className="section-header">
          <h2 className="section-title">What Our Clients Say</h2>
          <p className="section-subtitle">
            Don't just take our word for it. Here's what our satisfied clients have to say about their experience with LoanFlow.
          </p>
        </div>

        <div className="testimonials-grid">
          {visibleTestimonials.map((testimonial, index) => (
            <div key={`${testimonial.id}-${index}`} className="testimonial-card">
              <div className="testimonial-content">
                <div className="quote-icon">"</div>
                <p className="testimonial-text">{testimonial.content}</p>
                <div className="testimonial-rating">
                  {renderStars(testimonial.rating)}
                </div>
              </div>
              <div className="testimonial-author">
                <div className="author-info">
                  <h4 className="author-name">{testimonial.name}</h4>
                  <p className="author-location">{testimonial.location}</p>
                  <p className="loan-type">{testimonial.loan_type}</p>
                </div>
              </div>
            </div>
          ))}
        </div>

        <div className="testimonials-navigation">
          <div className="nav-dots">
            {activeTestimonials.map((_, index) => (
              <button
                key={index}
                className={`nav-dot ${index === currentIndex ? 'active' : ''}`}
                onClick={() => setCurrentIndex(index)}
                aria-label={`Go to testimonial ${index + 1}`}
              />
            ))}
          </div>
        </div>

        <div className="testimonials-stats">
          <div className="stat-item">
            <div className="stat-number">500+</div>
            <div className="stat-label">Happy Clients</div>
          </div>
          <div className="stat-item">
            <div className="stat-number">4.9/5</div>
            <div className="stat-label">Average Rating</div>
          </div>
          <div className="stat-item">
            <div className="stat-number">98%</div>
            <div className="stat-label">Satisfaction Rate</div>
          </div>
          <div className="stat-item">
            <div className="stat-number">24hr</div>
            <div className="stat-label">Average Approval</div>
          </div>
        </div>

        <div className="cta-section">
          <h3>Ready to Join Our Satisfied Clients?</h3>
          <p>Experience the LoanFlow difference for yourself. Apply today and see why thousands trust us with their lending needs.</p>
          <a href="#apply" className="cta-button">
            Apply Now
            <span className="button-arrow">→</span>
          </a>
        </div>
      </div>
    </section>
  );
};

export default Testimonials;