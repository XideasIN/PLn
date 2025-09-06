import React, { useState, useEffect } from 'react';
import './ClientDashboard.css';

const ClientDashboard = () => {
  const [activeSection, setActiveSection] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [profile, setProfile] = useState({});
  const [applications, setApplications] = useState([]);
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Form states
  const [loanForm, setLoanForm] = useState({
    amount: '',
    purpose: '',
    loan_type: 'personal',
    term_months: '12'
  });

  const [profileForm, setProfileForm] = useState({
    address: '',
    city: '',
    state_province: '',
    postal_zip_code: '',
    sin_ssn: '',
    date_of_birth: '',
    employment_status: '',
    annual_income: '',
    bank_name: '',
    account_number: '',
    routing_number: '',
    preferred_currency: 'USD'
  });

  useEffect(() => {
    fetchUserData();
    fetchProfile();
    fetchApplications();
    fetchDocuments();
  }, []);

  const fetchUserData = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/auth/me', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setUser(data.user);
      }
    } catch (err) {
      setError('Failed to fetch user data');
    } finally {
      setLoading(false);
    }
  };

  const fetchProfile = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/client/profile', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setProfile(data.profile);
        setProfileForm({
          address: data.profile.address || '',
          city: data.profile.city || '',
          state_province: data.profile.state_province || '',
          postal_zip_code: data.profile.postal_zip_code || '',
          sin_ssn: data.profile.sin_ssn || '',
          date_of_birth: data.profile.date_of_birth || '',
          employment_status: data.profile.employment_status || '',
          annual_income: data.profile.annual_income || '',
          bank_name: data.profile.bank_name || '',
          account_number: data.profile.account_number || '',
          routing_number: data.profile.routing_number || '',
          preferred_currency: data.profile.preferred_currency || 'USD'
        });
      }
    } catch (err) {
      setError('Failed to fetch profile');
    }
  };

  const fetchApplications = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/loan-applications', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setApplications(data.applications);
      }
    } catch (err) {
      setError('Failed to fetch applications');
    }
  };

  const fetchDocuments = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/documents', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setDocuments(data.documents);
      }
    } catch (err) {
      setError('Failed to fetch documents');
    }
  };

  const handleLoanSubmit = async (e) => {
    e.preventDefault();
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/loan-applications', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(loanForm)
      });
      
      if (response.ok) {
        setSuccess('Loan application submitted successfully!');
        setLoanForm({ amount: '', purpose: '', loan_type: 'personal', term_months: '12' });
        fetchApplications();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        const data = await response.json();
        setError(data.error || 'Failed to submit application');
      }
    } catch (err) {
      setError('Network error occurred');
    }
  };

  const handleProfileSubmit = async (e) => {
    e.preventDefault();
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/client/profile', {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(profileForm)
      });
      
      if (response.ok) {
        setSuccess('Profile updated successfully!');
        fetchProfile();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        const data = await response.json();
        setError(data.error || 'Failed to update profile');
      }
    } catch (err) {
      setError('Network error occurred');
    }
  };

  const handleDocumentUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', 'general');

    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/documents/upload', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });
      
      if (response.ok) {
        setSuccess('Document uploaded successfully!');
        fetchDocuments();
        setTimeout(() => setSuccess(''), 5000);
      } else {
        const data = await response.json();
        setError(data.error || 'Failed to upload document');
      }
    } catch (err) {
      setError('Network error occurred');
    }
  };

  const renderDashboard = () => {
    const totalApplications = applications.length;
    const pendingApplications = applications.filter(app => app.status === 'pending').length;
    const approvedApplications = applications.filter(app => app.status === 'approved').length;
    const totalDocuments = documents.length;

    return (
      <div className="dashboard-overview">
        <div className="welcome-section">
          <h2>Welcome back, {user?.first_name}!</h2>
          <p>Here's an overview of your loan applications and account status.</p>
        </div>

        <div className="stats-grid">
          <div className="stat-card">
            <div className="stat-icon">📄</div>
            <div className="stat-content">
              <div className="stat-number">{totalApplications}</div>
              <div className="stat-label">Total Applications</div>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon">⏳</div>
            <div className="stat-content">
              <div className="stat-number">{pendingApplications}</div>
              <div className="stat-label">Pending Review</div>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon">✅</div>
            <div className="stat-content">
              <div className="stat-number">{approvedApplications}</div>
              <div className="stat-label">Approved</div>
            </div>
          </div>
          <div className="stat-card">
            <div className="stat-icon">📁</div>
            <div className="stat-content">
              <div className="stat-number">{totalDocuments}</div>
              <div className="stat-label">Documents</div>
            </div>
          </div>
        </div>

        <div className="recent-activity">
          <h3>Recent Applications</h3>
          <div className="activity-list">
            {applications.slice(0, 3).map(app => (
              <div key={app.id} className="activity-item">
                <div className="activity-info">
                  <div className="activity-title">Loan Application #{app.id}</div>
                  <div className="activity-details">
                    ${app.amount?.toLocaleString()} - {app.purpose}
                  </div>
                  <div className="activity-date">
                    {new Date(app.created_at).toLocaleDateString()}
                  </div>
                </div>
                <div className={`activity-status status-${app.status}`}>
                  {app.status}
                </div>
              </div>
            ))}
            {applications.length === 0 && (
              <div className="no-data">No applications yet. Apply for your first loan!</div>
            )}
          </div>
        </div>
      </div>
    );
  };

  const renderLoanApplication = () => (
    <div className="loan-application-section">
      <h2>Apply for a Loan</h2>
      <div className="form-container">
        <form onSubmit={handleLoanSubmit} className="loan-form">
          <div className="form-group">
            <label>Loan Amount ($)</label>
            <input
              type="number"
              value={loanForm.amount}
              onChange={(e) => setLoanForm({...loanForm, amount: e.target.value})}
              placeholder="Enter loan amount"
              required
              min="1000"
              max="50000"
            />
          </div>
          
          <div className="form-group">
            <label>Loan Purpose</label>
            <textarea
              value={loanForm.purpose}
              onChange={(e) => setLoanForm({...loanForm, purpose: e.target.value})}
              placeholder="Describe the purpose of your loan"
              required
              rows="4"
            />
          </div>
          
          <div className="form-row">
            <div className="form-group">
              <label>Loan Type</label>
              <select
                value={loanForm.loan_type}
                onChange={(e) => setLoanForm({...loanForm, loan_type: e.target.value})}
              >
                <option value="personal">Personal Loan</option>
                <option value="business">Business Loan</option>
                <option value="auto">Auto Loan</option>
                <option value="home">Home Loan</option>
              </select>
            </div>
            
            <div className="form-group">
              <label>Term (Months)</label>
              <select
                value={loanForm.term_months}
                onChange={(e) => setLoanForm({...loanForm, term_months: e.target.value})}
              >
                <option value="6">6 Months</option>
                <option value="12">12 Months</option>
                <option value="24">24 Months</option>
                <option value="36">36 Months</option>
                <option value="48">48 Months</option>
                <option value="60">60 Months</option>
              </select>
            </div>
          </div>
          
          <button type="submit" className="submit-btn">
            Submit Application
          </button>
        </form>
      </div>
    </div>
  );

  const renderApplications = () => (
    <div className="applications-section">
      <h2>My Applications</h2>
      <div className="applications-list">
        {applications.map(app => (
          <div key={app.id} className="application-card">
            <div className="card-header">
              <div className="app-id">Application #{app.id}</div>
              <div className={`status-badge status-${app.status}`}>
                {app.status}
              </div>
            </div>
            <div className="card-content">
              <div className="app-details">
                <div className="detail-item">
                  <span className="label">Amount:</span>
                  <span className="value">${app.amount?.toLocaleString()}</span>
                </div>
                <div className="detail-item">
                  <span className="label">Purpose:</span>
                  <span className="value">{app.purpose}</span>
                </div>
                <div className="detail-item">
                  <span className="label">Type:</span>
                  <span className="value">{app.loan_type}</span>
                </div>
                <div className="detail-item">
                  <span className="label">Term:</span>
                  <span className="value">{app.term_months} months</span>
                </div>
                <div className="detail-item">
                  <span className="label">Applied:</span>
                  <span className="value">{new Date(app.created_at).toLocaleDateString()}</span>
                </div>
                {app.approval_date && (
                  <div className="detail-item">
                    <span className="label">Approved:</span>
                    <span className="value">{new Date(app.approval_date).toLocaleDateString()}</span>
                  </div>
                )}
                {app.interest_rate && (
                  <div className="detail-item">
                    <span className="label">Interest Rate:</span>
                    <span className="value">{app.interest_rate}%</span>
                  </div>
                )}
              </div>
            </div>
          </div>
        ))}
        {applications.length === 0 && (
          <div className="no-data">No applications found. Start by applying for a loan!</div>
        )}
      </div>
    </div>
  );

  const renderProfile = () => (
    <div className="profile-section">
      <h2>My Profile</h2>
      <div className="form-container">
        <form onSubmit={handleProfileSubmit} className="profile-form">
          <div className="form-section">
            <h3>Personal Information</h3>
            <div className="form-row">
              <div className="form-group">
                <label>First Name</label>
                <input type="text" value={user?.first_name || ''} disabled />
              </div>
              <div className="form-group">
                <label>Last Name</label>
                <input type="text" value={user?.last_name || ''} disabled />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label>Email</label>
                <input type="email" value={user?.email || ''} disabled />
              </div>
              <div className="form-group">
                <label>Phone</label>
                <input type="tel" value={user?.phone || ''} disabled />
              </div>
            </div>
            <div className="form-group">
              <label>Date of Birth</label>
              <input
                type="date"
                value={profileForm.date_of_birth}
                onChange={(e) => setProfileForm({...profileForm, date_of_birth: e.target.value})}
              />
            </div>
          </div>

          <div className="form-section">
            <h3>Address Information</h3>
            <div className="form-group">
              <label>Address</label>
              <input
                type="text"
                value={profileForm.address}
                onChange={(e) => setProfileForm({...profileForm, address: e.target.value})}
                placeholder="Enter your address"
              />
            </div>
            <div className="form-row">
              <div className="form-group">
                <label>City</label>
                <input
                  type="text"
                  value={profileForm.city}
                  onChange={(e) => setProfileForm({...profileForm, city: e.target.value})}
                  placeholder="Enter city"
                />
              </div>
              <div className="form-group">
                <label>State/Province</label>
                <input
                  type="text"
                  value={profileForm.state_province}
                  onChange={(e) => setProfileForm({...profileForm, state_province: e.target.value})}
                  placeholder="Enter state/province"
                />
              </div>
              <div className="form-group">
                <label>Postal/Zip Code</label>
                <input
                  type="text"
                  value={profileForm.postal_zip_code}
                  onChange={(e) => setProfileForm({...profileForm, postal_zip_code: e.target.value})}
                  placeholder="Enter postal/zip code"
                />
              </div>
            </div>
          </div>

          <div className="form-section">
            <h3>Employment & Financial Information</h3>
            <div className="form-row">
              <div className="form-group">
                <label>Employment Status</label>
                <select
                  value={profileForm.employment_status}
                  onChange={(e) => setProfileForm({...profileForm, employment_status: e.target.value})}
                >
                  <option value="">Select employment status</option>
                  <option value="employed">Employed</option>
                  <option value="self_employed">Self Employed</option>
                  <option value="unemployed">Unemployed</option>
                  <option value="retired">Retired</option>
                  <option value="student">Student</option>
                </select>
              </div>
              <div className="form-group">
                <label>Annual Income ($)</label>
                <input
                  type="number"
                  value={profileForm.annual_income}
                  onChange={(e) => setProfileForm({...profileForm, annual_income: e.target.value})}
                  placeholder="Enter annual income"
                />
              </div>
            </div>
            <div className="form-group">
              <label>SIN/SSN</label>
              <input
                type="text"
                value={profileForm.sin_ssn}
                onChange={(e) => setProfileForm({...profileForm, sin_ssn: e.target.value})}
                placeholder="Enter SIN/SSN"
              />
            </div>
          </div>

          <div className="form-section">
            <h3>Banking Information</h3>
            <div className="form-group">
              <label>Bank Name</label>
              <input
                type="text"
                value={profileForm.bank_name}
                onChange={(e) => setProfileForm({...profileForm, bank_name: e.target.value})}
                placeholder="Enter bank name"
              />
            </div>
            <div className="form-row">
              <div className="form-group">
                <label>Account Number</label>
                <input
                  type="text"
                  value={profileForm.account_number}
                  onChange={(e) => setProfileForm({...profileForm, account_number: e.target.value})}
                  placeholder="Enter account number"
                />
              </div>
              <div className="form-group">
                <label>Routing Number</label>
                <input
                  type="text"
                  value={profileForm.routing_number}
                  onChange={(e) => setProfileForm({...profileForm, routing_number: e.target.value})}
                  placeholder="Enter routing number"
                />
              </div>
            </div>
            <div className="form-group">
              <label>Preferred Currency</label>
              <select
                value={profileForm.preferred_currency}
                onChange={(e) => setProfileForm({...profileForm, preferred_currency: e.target.value})}
              >
                <option value="USD">USD - US Dollar</option>
                <option value="CAD">CAD - Canadian Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
              </select>
            </div>
          </div>
          
          <button type="submit" className="submit-btn">
            Update Profile
          </button>
        </form>
      </div>
    </div>
  );

  const renderDocuments = () => (
    <div className="documents-section">
      <h2>My Documents</h2>
      
      <div className="upload-section">
        <h3>Upload New Document</h3>
        <div className="upload-area">
          <input
            type="file"
            id="document-upload"
            onChange={handleDocumentUpload}
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
            style={{ display: 'none' }}
          />
          <label htmlFor="document-upload" className="upload-btn">
            📁 Choose File to Upload
          </label>
          <p className="upload-info">
            Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
          </p>
        </div>
      </div>

      <div className="documents-list">
        <h3>Uploaded Documents</h3>
        {documents.map(doc => (
          <div key={doc.id} className="document-card">
            <div className="doc-icon">📄</div>
            <div className="doc-info">
              <div className="doc-name">{doc.file_name}</div>
              <div className="doc-details">
                <span className="doc-type">{doc.document_type}</span>
                <span className="doc-size">{(doc.file_size / 1024).toFixed(1)} KB</span>
                <span className="doc-date">{new Date(doc.created_at).toLocaleDateString()}</span>
              </div>
            </div>
            <div className={`doc-status status-${doc.status}`}>
              {doc.status}
            </div>
          </div>
        ))}
        {documents.length === 0 && (
          <div className="no-data">No documents uploaded yet.</div>
        )}
      </div>
    </div>
  );

  if (loading) {
    return <div className="loading">Loading your dashboard...</div>;
  }

  return (
    <div className="client-dashboard">
      <div className="dashboard-header">
        <div className="header-content">
          <h1>Client Dashboard</h1>
          <div className="user-info">
            <span>Welcome, {user?.first_name} {user?.last_name}</span>
            <button 
              className="logout-btn"
              onClick={() => {
                localStorage.removeItem('access_token');
                window.location.href = '/';
              }}
            >
              Logout
            </button>
          </div>
        </div>
      </div>

      <div className="dashboard-nav">
        <button 
          className={`nav-btn ${activeSection === 'dashboard' ? 'active' : ''}`}
          onClick={() => setActiveSection('dashboard')}
        >
          📊 Dashboard
        </button>
        <button 
          className={`nav-btn ${activeSection === 'apply' ? 'active' : ''}`}
          onClick={() => setActiveSection('apply')}
        >
          📝 Apply for Loan
        </button>
        <button 
          className={`nav-btn ${activeSection === 'applications' ? 'active' : ''}`}
          onClick={() => setActiveSection('applications')}
        >
          📄 My Applications
        </button>
        <button 
          className={`nav-btn ${activeSection === 'profile' ? 'active' : ''}`}
          onClick={() => setActiveSection('profile')}
        >
          👤 My Profile
        </button>
        <button 
          className={`nav-btn ${activeSection === 'documents' ? 'active' : ''}`}
          onClick={() => setActiveSection('documents')}
        >
          📁 Documents
        </button>
      </div>

      <div className="dashboard-content">
        {error && <div className="error-message">{error}</div>}
        {success && <div className="success-message">{success}</div>}
        
        {activeSection === 'dashboard' && renderDashboard()}
        {activeSection === 'apply' && renderLoanApplication()}
        {activeSection === 'applications' && renderApplications()}
        {activeSection === 'profile' && renderProfile()}
        {activeSection === 'documents' && renderDocuments()}
      </div>
    </div>
  );
};

export default ClientDashboard;