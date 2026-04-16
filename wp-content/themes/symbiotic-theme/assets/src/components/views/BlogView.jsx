import React, { useState, useEffect } from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import { getPosts, getPost } from '../../utils/api.js';
import Icon, { AiAvatar } from '../shared/Icon.jsx';

export default function BlogView() {
  const { state, navigate } = useWorkspace();
  const postSlug = state.viewParams?.postSlug;

  if (postSlug) {
    return <BlogPostView slug={postSlug} />;
  }
  return <BlogListView />;
}

function BlogListView() {
  const { navigate } = useWorkspace();
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getPosts(1, 12)
      .then(data => setPosts(data || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="sym-view sym-view--blog">
      {/* Blog hero */}
      <div className="sym-page-banner" style={{ background: 'linear-gradient(135deg, rgba(157,51,214,0.1) 0%, rgba(96,165,250,0.05) 100%)' }}>
        <div className="sym-page-banner-icon">
          <Icon name="document" size={28} />
        </div>
        <h1 className="sym-page-banner-title">Blog</h1>
        <p className="sym-page-banner-sub">Printing tips, design guides, and industry insights.</p>
      </div>

      {loading ? (
        <div className="sym-blog-grid">
          {[1,2,3,4,5,6].map(i => (
            <div key={i} className="sym-blog-card sym-blog-card--skeleton">
              <div className="sym-skeleton" style={{ height: 160, borderRadius: '12px 12px 0 0' }} />
              <div style={{ padding: 16 }}>
                <div className="sym-skeleton" style={{ height: 14, width: '40%', marginBottom: 8 }} />
                <div className="sym-skeleton" style={{ height: 18, width: '90%', marginBottom: 8 }} />
                <div className="sym-skeleton" style={{ height: 14, width: '70%' }} />
              </div>
            </div>
          ))}
        </div>
      ) : posts.length > 0 ? (
        <div className="sym-blog-grid">
          {posts.map(post => {
            const featuredImg = post._embedded?.['wp:featuredmedia']?.[0]?.source_url;
            return (
              <button
                key={post.id}
                className="sym-blog-card"
                onClick={() => navigate('blog', { postSlug: post.slug })}
              >
                <div className={`sym-blog-card-img ${!featuredImg ? 'sym-blog-card-img--placeholder' : ''}`}>
                  {featuredImg ? (
                    <img src={featuredImg} alt={post.title?.rendered} loading="lazy" />
                  ) : (
                    <Icon name="document" size={32} />
                  )}
                </div>
                <div className="sym-blog-card-body">
                  <div className="sym-blog-card-meta">
                    <time>{new Date(post.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</time>
                  </div>
                  <h2 className="sym-blog-card-title" dangerouslySetInnerHTML={{ __html: post.title?.rendered }} />
                  <p className="sym-blog-card-excerpt" dangerouslySetInnerHTML={{ __html: post.excerpt?.rendered?.replace(/<[^>]+>/g, '').slice(0, 100) + '...' }} />
                  <span className="sym-blog-card-link">Read more →</span>
                </div>
              </button>
            );
          })}
        </div>
      ) : (
        <div className="sym-empty-state">
          <Icon name="document" size={40} />
          <p className="sym-empty-title">No posts yet</p>
          <p>Check back soon for printing tips and design guides.</p>
        </div>
      )}
    </div>
  );
}

function BlogPostView({ slug }) {
  const { navigate, sendMessage } = useWorkspace();
  const [post, setPost] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    getPost(slug)
      .then(data => {
        if (data && data.length > 0) setPost(data[0]);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [slug]);

  if (loading) {
    return (
      <div className="sym-view sym-view--blogpost">
        <div className="sym-skeleton" style={{ height: 16, width: '30%', marginBottom: 12 }} />
        <div className="sym-skeleton" style={{ height: 32, width: '80%', marginBottom: 24 }} />
        <div className="sym-skeleton sym-skeleton--card" style={{ height: 300, marginBottom: 24 }} />
        <div className="sym-skeleton sym-skeleton--row" />
        <div className="sym-skeleton sym-skeleton--row" />
        <div className="sym-skeleton sym-skeleton--row" style={{ width: '60%' }} />
      </div>
    );
  }

  if (!post) {
    return (
      <div className="sym-view sym-view--blogpost">
        <div className="sym-empty-state">
          <Icon name="alertCircle" size={40} />
          <p className="sym-empty-title">Post not found</p>
        </div>
      </div>
    );
  }

  const featuredImg = post._embedded?.['wp:featuredmedia']?.[0]?.source_url;
  const readingTime = Math.ceil((post.content?.rendered || '').replace(/<[^>]+>/g, '').split(/\s+/).length / 200);

  return (
    <div className="sym-view sym-view--blogpost">
      {/* Back + meta */}
      <button className="sym-blogpost-back" onClick={() => navigate('blog')}>
        <Icon name="chevronLeft" size={16} /> Back to Blog
      </button>

      <div className="sym-blogpost-meta">
        <time>{new Date(post.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</time>
        <span>{readingTime} min read</span>
      </div>

      <h1 className="sym-blogpost-title" dangerouslySetInnerHTML={{ __html: post.title?.rendered }} />

      {featuredImg && (
        <div className="sym-blogpost-hero">
          <img src={featuredImg} alt={post.title?.rendered} />
        </div>
      )}

      <div className="sym-prose" dangerouslySetInnerHTML={{ __html: post.content?.rendered || '' }} />

      {/* AI CTA */}
      <div className="sym-page-cta-bar">
        <div className="sym-page-cta-card">
          <AiAvatar size={32} />
          <div>
            <strong>Have questions about this topic?</strong>
            <p>Ask our AI Print Advisor for personalized guidance.</p>
          </div>
          <button className="sym-btn sym-btn--primary sym-page-cta-btn" onClick={() => {
            sendMessage(`Tell me more about ${post.title?.rendered?.replace(/<[^>]+>/g, '')}`);
          }}>
            Ask AI
          </button>
        </div>
      </div>
    </div>
  );
}
