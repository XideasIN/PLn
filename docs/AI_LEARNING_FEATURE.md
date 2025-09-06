# AI Learning Feature Documentation

## Overview

The AI Learning feature allows administrators to request the AI chatbot to learn from new documents and email templates. This enhances the chatbot's knowledge base and improves its ability to provide accurate and relevant responses to user queries.

## Features

### 1. AI Learning Requests
- **Request Creation**: Admins can create learning requests for email templates and document templates
- **Priority Levels**: Support for Low, Medium, and High priority requests
- **Status Tracking**: Real-time status updates (pending, processing, completed, failed)
- **Retry Mechanism**: Failed requests can be retried

### 2. Knowledge Base Management
- **Content Processing**: Automatic extraction of key information from documents
- **Content Indexing**: Searchable index for quick retrieval
- **Content Classification**: Automatic categorization (financial, communication, legal, general)
- **Statistics**: Comprehensive analytics on knowledge base usage

### 3. Search Functionality
- **Knowledge Base Search**: Search through learned content
- **Relevant Results**: Intelligent matching based on key phrases and terms
- **Detailed Results**: View content summaries, key phrases, and metadata

## How It Works

### 1. Content Processing Pipeline

When an admin creates a learning request, the system:

1. **Extracts Key Information**:
   - Key phrases and important terms
   - Structured data (dates, phone numbers, emails, URLs)
   - Content summary
   - Content type classification

2. **Updates Knowledge Base**:
   - Stores processed content with metadata
   - Creates searchable indexes
   - Updates chatbot response patterns

3. **Indexes Content**:
   - Creates searchable terms and phrases
   - Enables quick retrieval during chatbot interactions

### 2. Content Classification

The system automatically classifies content into categories:

- **Financial**: Contains loan, credit, payment, interest terms
- **Communication**: Contains email, contact, support terms
- **Legal**: Contains document, agreement, contract terms
- **General**: Other content types

### 3. Search and Retrieval

The knowledge base search:

- Matches user queries against indexed terms
- Returns relevant content with summaries
- Provides context and metadata for each result

## Usage Guide

### Accessing the AI Learning Feature

1. Navigate to the Admin Dashboard
2. Go to Chatbot Settings
3. Click on the "AI Learning" tab

### Creating a Learning Request

1. **Click "Request AI Learning"**
2. **Select Source Type**:
   - Email Template
   - Document Template

3. **Choose Source**:
   - Select from existing templates
   - Or enter custom content

4. **Set Priority**:
   - Low: Background processing
   - Medium: Normal priority
   - High: Immediate processing

5. **Review and Submit**

### Monitoring Learning Requests

The AI Learning tab shows:

- **Request Status**: Pending, Processing, Completed, Failed
- **Source Information**: Template name and type
- **Priority Level**: Visual indicators
- **Timestamps**: When requests were created and processed
- **Actions**: Retry failed requests

### Searching the Knowledge Base

1. **Enter Search Query**: Type relevant terms
2. **Click Search**: View matching results
3. **Review Results**: See content summaries and metadata

### Knowledge Base Statistics

View comprehensive statistics:

- **Total Entries**: Number of learned items
- **Indexed Terms**: Searchable terms and phrases
- **Content Types**: Distribution by category
- **Last Updated**: Most recent learning activity

## Technical Implementation

### Database Schema

```sql
CREATE TABLE ai_learning_requests (
    id INTEGER PRIMARY KEY,
    request_type VARCHAR(50) NOT NULL,
    source_id INTEGER,
    source_name VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    priority INTEGER DEFAULT 1,
    requested_by INTEGER NOT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    error_message TEXT,
    learning_metadata TEXT,
    FOREIGN KEY (requested_by) REFERENCES users(id)
);
```

### API Endpoints

#### Learning Requests
- `GET /api/admin/ai-learning/requests` - Get all learning requests
- `POST /api/admin/ai-learning/requests` - Create new learning request
- `GET /api/admin/ai-learning/requests/{id}` - Get specific request
- `POST /api/admin/ai-learning/requests/{id}/retry` - Retry failed request

#### Knowledge Base
- `GET /api/admin/ai-learning/sources` - Get available sources
- `POST /api/admin/ai-learning/knowledge-base/search` - Search knowledge base
- `GET /api/admin/ai-learning/knowledge-base/stats` - Get statistics

### Service Architecture

The AI Learning Service provides:

- **Content Processing**: Extract and analyze content
- **Knowledge Base Management**: Store and retrieve learned information
- **Search Functionality**: Find relevant content
- **Statistics**: Track usage and performance

## Best Practices

### Content Selection

1. **Choose Relevant Content**: Select templates that contain important information
2. **Prioritize Updates**: Use high priority for critical content
3. **Regular Updates**: Keep knowledge base current with new templates

### Request Management

1. **Monitor Status**: Check request status regularly
2. **Retry Failures**: Retry failed requests when appropriate
3. **Review Results**: Verify learning outcomes

### Search Optimization

1. **Use Specific Terms**: Search with relevant keywords
2. **Review Results**: Check content summaries for relevance
3. **Iterate**: Refine searches based on results

## Troubleshooting

### Common Issues

1. **Failed Requests**:
   - Check content format
   - Verify source availability
   - Review error messages

2. **No Search Results**:
   - Ensure content has been learned
   - Try different search terms
   - Check knowledge base statistics

3. **Processing Delays**:
   - Check request priority
   - Monitor system resources
   - Review processing logs

### Error Messages

- **"Source not found"**: Template doesn't exist
- **"Invalid content"**: Content format issues
- **"Processing failed"**: System error during processing

## Future Enhancements

### Planned Features

1. **Advanced NLP**: Integration with advanced natural language processing
2. **Machine Learning**: Improved content classification and relevance scoring
3. **Bulk Operations**: Process multiple templates simultaneously
4. **Content Validation**: Automatic content quality assessment
5. **Integration**: Connect with external knowledge sources

### Performance Optimizations

1. **Caching**: Implement result caching for faster searches
2. **Background Processing**: Asynchronous processing for better performance
3. **Index Optimization**: Improved search indexing algorithms

## Support

For technical support or questions about the AI Learning feature:

1. Check the troubleshooting section
2. Review system logs for errors
3. Contact the development team
4. Submit feature requests through the admin panel 