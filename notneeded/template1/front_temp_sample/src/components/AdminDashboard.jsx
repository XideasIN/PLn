import React, { useState, useEffect } from 'react';
import './AdminDashboard.css';

const AdminDashboard = () => {
  const [activeSection, setActiveSection] = useState('dashboard');
  const [dashboardStats, setDashboardStats] = useState({
    total_users: 0,
    total_applications: 0,
    pending_applications: 0,
    approved_applications: 0,
    total_documents: 0,
    pending_documents: 0
  });
  const [users, setUsers] = useState([]);
  const [applications, setApplications] = useState([]);
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/admin/dashboard', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setDashboardStats(data.stats);
      } else {
        setError('Failed to fetch dashboard data');
      }
    } catch (err) {
      setError('Network error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch('/api/users', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        setUsers(data.users);
      }
    } catch (err) {
      setError('Failed to fetch users');
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

  const updateLoanStatus = async (appId, newStatus, notes = '') => {
    try {
      const token = localStorage.getItem('access_token');
      const response = await fetch(`/api/loan-applications/${appId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status: newStatus, notes })
      });
      
      if (response.ok) {
        fetchApplications();
        fetchDashboardData();
      } else {
        setError('Failed to update loan status');
      }
    } catch (err) {
      setError('Network error occurred');
    }
  };

  const handleSectionChange = (section) => {
    setActiveSection(section);
    if (section === 'users') fetchUsers();
    if (section === 'applications') fetchApplications();
    if (section === 'documents') fetchDocuments();
  };

  const renderDashboard = () => (
    <div className="workspace-insights">
      <div className="insights-header">
        <h2>Workspace Insights</h2>
        <div className="filter-controls">
          <select className="filter-select">
            <option>Filter by: Plans</option>
            <option>Filter by: Users</option>
            <option>Filter by: Status</option>
          </select>
          <select className="filter-select">
            <option>Filter by: Team</option>
            <option>Filter by: Individual</option>
          </select>
        </div>
      </div>

      <div className="audit-section">
        <h3>Workspace Audit</h3>
        <div className="audit-metrics">
          <div className="metric-card">
            <div className="metric-number">{dashboardStats.total_users}</div>
            <div className="metric-label">USERS</div>
            <div className="metric-description">Total user accounts</div>
          </div>
          <div className="metric-card">
            <div className="metric-number">{dashboardStats.total_applications}</div>
            <div className="metric-label">PLANS</div>
            <div className="metric-description">Total loan applications</div>
          </div>
          <div className="metric-card">
            <div className="metric-number">{Math.round((dashboardStats.approved_applications / Math.max(dashboardStats.total_applications, 1)) * 100)}</div>
            <div className="metric-label">KEY RESULTS</div>
            <div className="metric-description">% loan approval rate</div>
          </div>
          <div className="metric-card">
            <div className="metric-number">{dashboardStats.total_documents}</div>
            <div className="metric-label">TASKS</div>
            <div className="metric-description">Total documents</div>
          </div>
        </div>
      </div>

      <div className="audit-score-section">
        <div className="overall-score">
          <h4>OVERALL AUDIT SCORE</h4>
          <div className="score-circle">
            <div className="score-number">75%</div>
          </div>
        </div>
        
        <div className="audit-breakdown">
          <div className="audit-item">
            <span className="audit-label">User Audit</span>
            <div className="progress-bar">
              <div className="progress-fill" style={{width: '75%'}}></div>
            </div>
            <span className="audit-percentage">75% 25%</span>
          </div>
          <div className="audit-item">
            <span className="audit-label">Plan Audit</span>
            <div className="progress-bar">
              <div className="progress-fill" style={{width: '75%'}}></div>
            </div>
            <span className="audit-percentage">75% 25%</span>
          </div>
          <div className="audit-item">
            <span className="audit-label">Key Results Audit</span>
            <div className="progress-bar">
              <div className="progress-fill" style={{width: '80%'}}></div>
            </div>
            <span className="audit-percentage">80% 20%</span>
          </div>
          <div className="audit-item">
            <span className="audit-label">Tasks Audit</span>
            <div className="progress-bar">
              <div className="progress-fill" style={{width: '75%'}}></div>
            </div>
            <span className="audit-percentage">75% 25%</span>
          </div>
        </div>
      </div>

      <div className="recent-activities">
        <h4>Recent Activities</h4>
        <div className="activity-charts">
          <div className="chart-section">
            <h5>QUICK CREATED</h5>
            <div className="bar-chart">
              {[20, 40, 35, 45, 30, 25, 40].map((height, index) => (
                <div key={index} className="bar" style={{height: `${height}px`}}></div>
              ))}
            </div>
          </div>
          <div className="chart-section">
            <h5>KEY RESULTS VIEWED</h5>
            <div className="bar-chart purple">
              {[30, 25, 40, 35, 20, 30, 45].map((height, index) => (
                <div key={index} className="bar" style={{height: `${height}px`}}></div>
              ))}
            </div>
          </div>
          <div className="chart-section">
            <h5>TASKS UPDATED</h5>
            <div className="bar-chart blue">
              {[35, 40, 30, 50, 25, 35, 40].map((height, index) => (
                <div key={index} className="bar" style={{height: `${height}px`}}></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  const renderUsers = () => (
    <div className="users-section">
      <h2>User Management</h2>
      <div className="table-container">
        <table className="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map(user => (
              <tr key={user.id}>
                <td>{user.first_name} {user.last_name}</td>
                <td>{user.email}</td>
                <td><span className={`role-badge ${user.role}`}>{user.role}</span></td>
                <td><span className={`status-badge ${user.is_active ? 'active' : 'inactive'}`}>
                  {user.is_active ? 'Active' : 'Inactive'}
                </span></td>
                <td>{new Date(user.created_at).toLocaleDateString()}</td>
                <td>
                  <button className="btn-small">Edit</button>
                  <button className="btn-small danger">Deactivate</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  const renderApplications = () => (
    <div className="applications-section">
      <h2>Loan Applications</h2>
      <div className="table-container">
        <table className="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Applicant</th>
              <th>Amount</th>
              <th>Purpose</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {applications.map(app => (
              <tr key={app.id}>
                <td>#{app.id}</td>
                <td>{app.user?.first_name} {app.user?.last_name}</td>
                <td>${app.amount?.toLocaleString()}</td>
                <td>{app.purpose}</td>
                <td><span className={`status-badge ${app.status}`}>{app.status}</span></td>
                <td>{new Date(app.created_at).toLocaleDateString()}</td>
                <td>
                  {app.status === 'pending' && (
                    <>
                      <button 
                        className="btn-small success"
                        onClick={() => updateLoanStatus(app.id, 'approved')}
                      >
                        Approve
                      </button>
                      <button 
                        className="btn-small danger"
                        onClick={() => updateLoanStatus(app.id, 'rejected')}
                      >
                        Reject
                      </button>
                    </>
                  )}
                  {app.status === 'approved' && (
                    <button 
                      className="btn-small primary"
                      onClick={() => updateLoanStatus(app.id, 'disbursed')}
                    >
                      Disburse
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  const renderDocuments = () => (
    <div className="documents-section">
      <h2>Document Management</h2>
      <div className="table-container">
        <table className="data-table">
          <thead>
            <tr>
              <th>File Name</th>
              <th>Type</th>
              <th>User</th>
              <th>Size</th>
              <th>Status</th>
              <th>Uploaded</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {documents.map(doc => (
              <tr key={doc.id}>
                <td>{doc.file_name}</td>
                <td><span className={`type-badge ${doc.document_type}`}>{doc.document_type}</span></td>
                <td>{doc.user?.first_name} {doc.user?.last_name}</td>
                <td>{(doc.file_size / 1024).toFixed(1)} KB</td>
                <td><span className={`status-badge ${doc.status}`}>{doc.status}</span></td>
                <td>{new Date(doc.created_at).toLocaleDateString()}</td>
                <td>
                  <button className="btn-small">View</button>
                  <button className="btn-small">Download</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  if (loading) {
    return <div className="loading">Loading dashboard...</div>;
  }

  return (
    <div className="admin-dashboard">
      <div className="sidebar">
        <div className="sidebar-header">
          <div className="logo">
            <div className="logo-icon">⚡</div>
            <span>tability</span>
          </div>
        </div>
        
        <nav className="sidebar-nav">
          <div className="nav-section">
            <div className="nav-item">
              <span className="nav-icon">🏠</span>
              <span>Home</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">👤</span>
              <span>My Profile</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">📋</span>
              <span>Plans</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">🗺️</span>
              <span>Strategy map</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">📊</span>
              <span>Reports</span>
            </div>
          </div>
          
          <div className="nav-section">
            <div 
              className={`nav-item ${activeSection === 'dashboard' ? 'active' : ''}`}
              onClick={() => handleSectionChange('dashboard')}
            >
              <span className="nav-icon">📈</span>
              <span>Insights</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">👥</span>
              <span>Dashboards</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">⚙️</span>
              <span>Shortcuts</span>
            </div>
          </div>
          
          <div className="nav-section">
            <div 
              className={`nav-item ${activeSection === 'users' ? 'active' : ''}`}
              onClick={() => handleSectionChange('users')}
            >
              <span className="nav-icon">👤</span>
              <span>Users</span>
            </div>
            <div 
              className={`nav-item ${activeSection === 'applications' ? 'active' : ''}`}
              onClick={() => handleSectionChange('applications')}
            >
              <span className="nav-icon">📄</span>
              <span>Applications</span>
            </div>
            <div 
              className={`nav-item ${activeSection === 'documents' ? 'active' : ''}`}
              onClick={() => handleSectionChange('documents')}
            >
              <span className="nav-icon">📁</span>
              <span>Documents</span>
            </div>
            <div className="nav-item">
              <span className="nav-icon">⚙️</span>
              <span>Settings</span>
            </div>
          </div>
          
          <div className="nav-section">
            <div className="nav-item">
              <span className="nav-icon">👥</span>
              <span>Teams</span>
            </div>
          </div>
        </nav>
      </div>
      
      <div className="main-content">
        <div className="content-header">
          <div className="search-bar">
            <input type="text" placeholder="Search anything..." />
            <button className="search-btn">🔍</button>
          </div>
          <div className="header-actions">
            <button className="notification-btn">🔔</button>
            <div className="user-menu">
              <div className="user-avatar">👤</div>
            </div>
          </div>
        </div>
        
        <div className="content-body">
          {error && <div className="error-message">{error}</div>}
          {activeSection === 'dashboard' && renderDashboard()}
          {activeSection === 'users' && renderUsers()}
          {activeSection === 'applications' && renderApplications()}
          {activeSection === 'documents' && renderDocuments()}
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;