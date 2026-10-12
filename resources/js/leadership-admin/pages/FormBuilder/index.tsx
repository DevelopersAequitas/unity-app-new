import React, { useEffect, useState } from 'react';
import { formApi, campaignApi } from '../../api/services';
import { FormTemplate, FormSection, FormQuestion, Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import {
    Plus,
    FileText,
    Eye,
    Settings,
    Layers,
    Trash2,
    CheckCircle,
    HelpCircle,
    MoveRight,
    Sparkles,
} from 'lucide-react';

export const FormBuilderPage: React.FC = () => {
    const [templates, setTemplates] = useState<FormTemplate[]>([]);
    const [selectedTemplate, setSelectedTemplate] = useState<FormTemplate | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeSectionId, setActiveSectionId] = useState<string | null>(null);
    const [editingQuestion, setEditingQuestion] = useState<Partial<FormQuestion> | null>(null);
    const [previewMode, setPreviewMode] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [successMsg, setSuccessMsg] = useState<string | null>(null);

    // New Section Form
    const [newSectionTitle, setNewSectionTitle] = useState<string>('');
    const [isAddingSection, setIsAddingSection] = useState<boolean>(false);

    // New Template Modal
    const [isNewTemplateModal, setIsNewTemplateModal] = useState<boolean>(false);
    const [newTemplateData, setNewTemplateData] = useState({
        title: '',
        form_type: 'nomination' as 'nomination' | 'jury_evaluation',
        description: '',
    });

    const loadTemplates = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await formApi.getTemplates();
            const list = res.data.data || [];
            setTemplates(list);
            if (list.length > 0 && !selectedTemplate) {
                loadTemplateDetail(list[0].id);
            }
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load form templates.');
        } finally {
            setIsLoading(false);
        }
    };

    const loadTemplateDetail = async (id: string) => {
        try {
            const res = await formApi.getTemplate(id);
            setSelectedTemplate(res.data.data);
            if (res.data.data.sections && res.data.data.sections.length > 0) {
                setActiveSectionId(res.data.data.sections[0].id);
            }
        } catch (err: any) {
            setError('Failed to load template sections and questions.');
        }
    };

    useEffect(() => {
        loadTemplates();
    }, []);

    const handleCreateTemplate = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            const res = await formApi.createTemplate(newTemplateData);
            setIsNewTemplateModal(false);
            setSuccessMsg('Form template created successfully.');
            loadTemplates();
            loadTemplateDetail(res.data.data.id);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to create template.');
        }
    };

    const handleAddSection = async () => {
        if (!selectedTemplate || !newSectionTitle.trim()) return;
        try {
            await formApi.createSection({
                template_id: selectedTemplate.id,
                title: newSectionTitle,
                sort_order: (selectedTemplate.sections?.length || 0) + 1,
            });
            setNewSectionTitle('');
            setIsAddingSection(false);
            loadTemplateDetail(selectedTemplate.id);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to add section.');
        }
    };

    const handleSaveQuestion = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeSectionId || !editingQuestion) return;

        try {
            if (editingQuestion.id) {
                await formApi.updateQuestion(editingQuestion.id, editingQuestion);
            } else {
                await formApi.createQuestion({
                    ...editingQuestion,
                    section_id: activeSectionId,
                    field_key: editingQuestion.field_key || editingQuestion.label?.toLowerCase().replace(/[^a-z0-9]/g, '_'),
                    sort_order: 1,
                    is_active: true,
                });
            }
            setEditingQuestion(null);
            if (selectedTemplate) loadTemplateDetail(selectedTemplate.id);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to save question.');
        }
    };

    const handleDeleteQuestion = async (questionId: string) => {
        if (!window.confirm('Are you sure you want to delete this question?')) return;
        try {
            await formApi.deleteQuestion(questionId);
            if (selectedTemplate) loadTemplateDetail(selectedTemplate.id);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to delete question.');
        }
    };

    const activeSection = selectedTemplate?.sections?.find((s) => s.id === activeSectionId);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Dynamic Form Builder</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Configure Form 1 (Nomination) and Form 2 (Jury Evaluation) schemas with live interactive previews
                    </p>
                </div>
                <div className="d-flex align-items-center gap-2">
                    <button
                        onClick={() => setPreviewMode(!previewMode)}
                        className={`btn ${previewMode ? 'btn-primary' : 'btn-outline-secondary'} btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5`}
                    >
                        <Eye size={16} /> {previewMode ? 'Exit Live Preview' : 'Live Form Preview'}
                    </button>
                    <button
                        onClick={() => setIsNewTemplateModal(true)}
                        className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 fw-semibold"
                    >
                        <Plus size={16} /> New Form Template
                    </button>
                </div>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{error}</div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {successMsg && (
                <div className="alert alert-success rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{successMsg}</div>
                    <button className="btn btn-sm btn-outline-success" onClick={() => setSuccessMsg(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Template Selector Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="d-flex align-items-center gap-3 flex-wrap">
                    <span className="text-muted small fw-semibold text-uppercase tracking-wider">Active Template:</span>
                    <div className="d-flex gap-2 flex-wrap">
                        {templates.map((tpl) => (
                            <button
                                key={tpl.id}
                                onClick={() => loadTemplateDetail(tpl.id)}
                                className={`btn btn-sm rounded-3 px-3 py-1.5 d-flex align-items-center gap-2 ${
                                    selectedTemplate?.id === tpl.id ? 'btn-indigo text-white fw-semibold shadow-xs' : 'btn-light border'
                                }`}
                                style={selectedTemplate?.id === tpl.id ? { backgroundColor: '#6366f1' } : {}}
                            >
                                <FileText size={14} />
                                <span>{tpl.title}</span>
                                <span className="badge bg-white text-dark small" style={{ fontSize: '0.65rem' }}>
                                    v{tpl.version}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            {/* If in Preview Mode, render Interactive Preview */}
            {previewMode && selectedTemplate ? (
                <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                    <div className="border-bottom pb-3 mb-4">
                        <span className="badge bg-success-subtle text-success rounded-pill px-3 py-1 mb-2">Live Public Candidate Preview</span>
                        <h3 className="h5 fw-bold text-dark mb-1">{selectedTemplate.title}</h3>
                        <p className="text-muted small mb-0">{selectedTemplate.description || 'Fill out the form sections below'}</p>
                    </div>

                    <div className="row g-4">
                        {selectedTemplate.sections?.map((sec, secIdx) => (
                            <div key={sec.id} className="col-12">
                                <div className="p-4 rounded-4 bg-light border">
                                    <h6 className="fw-bold text-dark mb-1">
                                        Section {secIdx + 1}: {sec.title}
                                    </h6>
                                    {sec.description && <p className="text-muted small mb-3">{sec.description}</p>}

                                    <div className="row g-3">
                                        {sec.questions?.map((q) => (
                                            <div key={q.id} className="col-12 col-md-6">
                                                <label className="form-label small fw-semibold text-dark">
                                                    {q.label} {q.is_required && <span className="text-danger">*</span>}
                                                </label>

                                                {q.field_type === 'textarea' ? (
                                                    <textarea className="form-control form-control-sm" placeholder={q.placeholder || ''} rows={3} />
                                                ) : q.field_type === 'select' ? (
                                                    <select className="form-select form-select-sm">
                                                        <option value="">Choose an option...</option>
                                                        {q.options?.map((opt) => (
                                                            <option key={opt.id} value={opt.value}>
                                                                {opt.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : q.field_type === 'file' ? (
                                                    <input type="file" className="form-control form-control-sm" />
                                                ) : (
                                                    <input
                                                        type={q.field_type === 'number' ? 'number' : q.field_type === 'email' ? 'email' : 'text'}
                                                        className="form-control form-control-sm"
                                                        placeholder={q.placeholder || ''}
                                                    />
                                                )}
                                                {q.help_text && <small className="text-muted">{q.help_text}</small>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                /* 3-Column Structured Builder Interface */
                <div className="row g-3 mb-4">
                    {/* Area 1: Sections & Question Tree */}
                    <div className="col-12 col-lg-4">
                        <div className="card border-0 shadow-xs rounded-4 bg-white p-3 h-100">
                            <div className="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <h6 className="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                                    <Layers size={16} className="text-indigo" /> Sections
                                </h6>
                                <button
                                    onClick={() => setIsAddingSection(true)}
                                    className="btn btn-sm btn-outline-primary py-0.5 px-2 rounded-2"
                                >
                                    + Add
                                </button>
                            </div>

                            {isAddingSection && (
                                <div className="p-2 mb-3 bg-light rounded-3 border">
                                    <input
                                        type="text"
                                        className="form-control form-control-sm mb-2"
                                        placeholder="Section Title (e.g. Executive Profile)"
                                        value={newSectionTitle}
                                        onChange={(e) => setNewSectionTitle(e.target.value)}
                                    />
                                    <div className="d-flex gap-2">
                                        <button onClick={handleAddSection} className="btn btn-primary btn-sm py-1 px-2.5">
                                            Save Section
                                        </button>
                                        <button onClick={() => setIsAddingSection(false)} className="btn btn-light btn-sm py-1 px-2">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="list-group list-group-flush">
                                {selectedTemplate?.sections?.map((sec, idx) => (
                                    <div
                                        key={sec.id}
                                        onClick={() => setActiveSectionId(sec.id)}
                                        className={`list-group-item list-group-item-action rounded-3 mb-2 p-2.5 border cursor-pointer ${
                                            activeSectionId === sec.id ? 'bg-indigo-subtle border-indigo' : ''
                                        }`}
                                        style={activeSectionId === sec.id ? { borderColor: '#6366f1', background: 'rgba(99, 102, 241, 0.08)' } : {}}
                                    >
                                        <div className="d-flex align-items-center justify-content-between">
                                            <div className="fw-semibold text-dark">
                                                {idx + 1}. {sec.title}
                                            </div>
                                            <span className="badge bg-light text-muted border small">{sec.questions?.length || 0} fields</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Area 2: Questions in Selected Section */}
                    <div className="col-12 col-lg-8">
                        <div className="card border-0 shadow-xs rounded-4 bg-white p-4 h-100">
                            {activeSection ? (
                                <>
                                    <div className="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                        <div>
                                            <h5 className="fw-bold text-dark mb-0">{activeSection.title}</h5>
                                            <span className="text-muted small">Configure form input fields for this section</span>
                                        </div>
                                        <button
                                            onClick={() =>
                                                setEditingQuestion({
                                                    label: '',
                                                    field_type: 'text',
                                                    is_required: true,
                                                    placeholder: '',
                                                    help_text: '',
                                                })
                                            }
                                            className="btn btn-primary btn-sm rounded-3 px-3 py-1.5 fw-semibold"
                                        >
                                            <Plus size={14} className="me-1" /> Add Question
                                        </button>
                                    </div>

                                    {/* Question Form if Adding/Editing */}
                                    {editingQuestion && (
                                        <div className="p-3 mb-4 rounded-4 bg-light border">
                                            <h6 className="fw-bold text-dark mb-3">
                                                {editingQuestion.id ? 'Edit Question' : 'New Question Configuration'}
                                            </h6>
                                            <form onSubmit={handleSaveQuestion}>
                                                <div className="row g-2">
                                                    <div className="col-12 col-md-8">
                                                        <label className="form-label small fw-semibold">Question Label *</label>
                                                        <input
                                                            type="text"
                                                            className="form-control form-control-sm"
                                                            placeholder="e.g. Total Years in Leadership Roles"
                                                            value={editingQuestion.label || ''}
                                                            onChange={(e) => setEditingQuestion({ ...editingQuestion, label: e.target.value })}
                                                            required
                                                        />
                                                    </div>

                                                    <div className="col-12 col-md-4">
                                                        <label className="form-label small fw-semibold">Field Type *</label>
                                                        <select
                                                            className="form-select form-select-sm"
                                                            value={editingQuestion.field_type || 'text'}
                                                            onChange={(e) =>
                                                                setEditingQuestion({ ...editingQuestion, field_type: e.target.value as any })
                                                            }
                                                        >
                                                            <option value="text">Short Text</option>
                                                            <option value="textarea">Long Text / Paragraph</option>
                                                            <option value="number">Number</option>
                                                            <option value="email">Email</option>
                                                            <option value="phone">Phone Number</option>
                                                            <option value="date">Date</option>
                                                            <option value="url">URL / Website Link</option>
                                                            <option value="select">Dropdown Select</option>
                                                            <option value="radio">Radio Buttons</option>
                                                            <option value="checkbox">Checkboxes</option>
                                                            <option value="file">File Upload</option>
                                                            <option value="declaration">Declaration / Consent</option>
                                                        </select>
                                                    </div>

                                                    <div className="col-12 col-md-6">
                                                        <label className="form-label small fw-semibold">Placeholder</label>
                                                        <input
                                                            type="text"
                                                            className="form-control form-control-sm"
                                                            value={editingQuestion.placeholder || ''}
                                                            onChange={(e) =>
                                                                setEditingQuestion({ ...editingQuestion, placeholder: e.target.value })
                                                            }
                                                        />
                                                    </div>

                                                    <div className="col-12 col-md-6">
                                                        <label className="form-label small fw-semibold">Help Text</label>
                                                        <input
                                                            type="text"
                                                            className="form-control form-control-sm"
                                                            value={editingQuestion.help_text || ''}
                                                            onChange={(e) =>
                                                                setEditingQuestion({ ...editingQuestion, help_text: e.target.value })
                                                            }
                                                        />
                                                    </div>

                                                    <div className="col-12 mt-2">
                                                        <div className="form-check">
                                                            <input
                                                                type="checkbox"
                                                                className="form-check-input"
                                                                id="chkRequired"
                                                                checked={editingQuestion.is_required ?? true}
                                                                onChange={(e) =>
                                                                    setEditingQuestion({ ...editingQuestion, is_required: e.target.checked })
                                                                }
                                                            />
                                                            <label className="form-check-label small" htmlFor="chkRequired">
                                                                Required Field
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div className="col-12 mt-3 d-flex gap-2">
                                                        <button type="submit" className="btn btn-primary btn-sm px-3 fw-semibold">
                                                            Save Question
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="btn btn-light btn-sm px-3"
                                                            onClick={() => setEditingQuestion(null)}
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    )}

                                    {/* Questions Table */}
                                    <div className="table-responsive">
                                        <table className="table table-hover align-middle mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Field Label</th>
                                                    <th>Type</th>
                                                    <th>Required</th>
                                                    <th className="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {activeSection.questions && activeSection.questions.length > 0 ? (
                                                    activeSection.questions.map((q) => (
                                                        <tr key={q.id}>
                                                            <td>
                                                                <div className="fw-semibold text-dark">{q.label}</div>
                                                                <div className="text-muted small font-monospace">{q.field_key}</div>
                                                            </td>
                                                            <td>
                                                                <span className="badge bg-light text-dark border">{q.field_type}</span>
                                                            </td>
                                                            <td>
                                                                {q.is_required ? (
                                                                    <span className="badge bg-danger-subtle text-danger">Required</span>
                                                                ) : (
                                                                    <span className="badge bg-secondary-subtle text-secondary">Optional</span>
                                                                )}
                                                            </td>
                                                            <td className="text-end">
                                                                <button
                                                                    onClick={() => setEditingQuestion(q)}
                                                                    className="btn btn-link btn-sm text-primary py-0 px-2"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDeleteQuestion(q.id)}
                                                                    className="btn btn-link btn-sm text-danger py-0 px-2"
                                                                >
                                                                    <Trash2 size={14} />
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))
                                                ) : (
                                                    <tr>
                                                        <td colSpan={4} className="text-center py-4 text-muted">
                                                            No questions configured in this section yet. Click "+ Add Question" to begin.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            ) : (
                                <div className="text-center py-5 text-muted">
                                    Select or create a section from the left column to configure its questions.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Create Template Modal */}
            {isNewTemplateModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleCreateTemplate}>
                                <div className="modal-header border-bottom-0">
                                    <h5 className="modal-title fw-bold text-dark">Create Form Template</h5>
                                    <button type="button" className="btn-close" onClick={() => setIsNewTemplateModal(false)}></button>
                                </div>
                                <div className="modal-body py-3">
                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Template Title *</label>
                                        <input
                                            type="text"
                                            className="form-control"
                                            placeholder="e.g. 2026 DED Nomination Comprehensive Form"
                                            value={newTemplateData.title}
                                            onChange={(e) => setNewTemplateData({ ...newTemplateData, title: e.target.value })}
                                            required
                                        />
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Form Type *</label>
                                        <select
                                            className="form-select"
                                            value={newTemplateData.form_type}
                                            onChange={(e) =>
                                                setNewTemplateData({
                                                    ...newTemplateData,
                                                    form_type: e.target.value as 'nomination' | 'jury_evaluation',
                                                })
                                            }
                                        >
                                            <option value="nomination">Form 1: Nomination Form</option>
                                            <option value="jury_evaluation">Form 2: Jury Evaluation Form</option>
                                        </select>
                                    </div>

                                    <div className="mb-3">
                                        <label className="form-label small fw-semibold text-dark">Description</label>
                                        <textarea
                                            className="form-control"
                                            rows={2}
                                            value={newTemplateData.description}
                                            onChange={(e) => setNewTemplateData({ ...newTemplateData, description: e.target.value })}
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light" onClick={() => setIsNewTemplateModal(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary fw-semibold px-4">
                                        Create Template
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
