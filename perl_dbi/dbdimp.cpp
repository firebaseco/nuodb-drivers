#include "dbdimp.h"

DBISTATE_DECLARE;

void dbd_init(dbistate_t* dbistate)
{
	DBISTATE_INIT;  /*  Initialize the DBI macros  */
	DBIS = dbistate;
}

int dbd_db_login6_sv(SV *dbh, imp_dbh_t *imp_dbh, SV *dbname, SV *uid, SV *pwd, SV *attr)
{

	SV* sv;
	SV** svp;
	HV* hv;
	STRLEN db_len;

	// Obtain the private attributes that are stashed in imp_dbh by DBI::NuoDB::connect
	sv = DBIc_IMP_DATA(imp_dbh);
	
	if (!sv || !SvROK(sv)) {
		return FALSE;
	}

	hv = (HV*) SvRV(sv);
	if (SvTYPE(hv) != SVt_PVHV)
		return FALSE;

	NuoDB::Connection *conn = createConnection();

	NuoDB::Properties *properties = conn->allocProperties();
	
	if (SvOK(uid))
		properties->putValue("user", SvPV_nolen(uid));

	if (SvOK(pwd))
		properties->putValue("password", SvPV_nolen(pwd));

	if ((svp = hv_fetch(hv, "schema", 6, FALSE)))
		properties->putValue("schema", SvPV(*svp, db_len));
		
	try {
		conn->openDatabase(SvPV_nolen(dbname), properties);
		imp_dbh->conn = conn;

		DBIc_ACTIVE_on(imp_dbh);
                DBIc_IMPSET_on(imp_dbh);
	} catch (NuoDB::SQLException& xcp) {
		do_error(dbh, xcp.getSqlcode(), (char *) xcp.getText());
		conn->close();
		imp_dbh->conn = NULL;
		return FALSE;
	}

	return TRUE;
}

int dbd_st_prepare_sv(SV *sth, imp_sth_t *imp_sth, SV *statement, SV *attribs)
{
	D_imp_dbh_from_sth;

	if (!imp_dbh->conn)
		return FALSE;

	sv_utf8_decode(statement);
	char *sql = SvPV_nolen(statement);

	try {
		imp_sth->pstmt = imp_dbh->conn->prepareStatement(sql);
		DBIc_IMPSET_on(imp_sth);

		NuoDB::ParameterMetaData* md = imp_sth->pstmt->getParameterMetaData();
		DBIc_NUM_PARAMS(imp_sth) = md->getParameterCount();
	} catch (NuoDB::SQLException& xcp) {
		do_error(sth, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}
	
	return TRUE;
}

int dbd_st_execute(SV* sth, imp_sth_t* imp_sth)
{
	try {
		DBIc_ACTIVE_off(imp_sth);
		imp_sth->rs = NULL;
		if (imp_sth->pstmt->execute()) {
			imp_sth->rs = imp_sth->pstmt->getResultSet();
	
			NuoDB::ResultSetMetaData *md = imp_sth->rs->getMetaData();
			DBIc_NUM_FIELDS(imp_sth) = md->getColumnCount();
		}
	} catch (NuoDB::SQLException& xcp) {
		do_error(sth, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}
	
	return TRUE;
}

AV* dbd_st_fetch(SV *sth, imp_sth_t* imp_sth)
{
	AV* av;
	int i;

	NuoDB::ResultSet *rs = imp_sth->rs;

	if (!rs) {
		return Nullav;
	}

	int numFields = DBIc_NUM_FIELDS(imp_sth);

	if (!rs->next()) {
		DBIc_ACTIVE_off(imp_sth);
		return Nullav;
	}

	av = DBIc_DBISTATE(imp_sth)->get_fbav(imp_sth);

	for (i = 0; i < numFields; i++) {
		SV *sv = AvARRAY(av)[i];

		const char * str = rs->getString(i + 1);

		if (rs->wasNull()) {
			(void) SvOK_off(sv);
		} else {
			sv_setpvn(sv, str, strlen(str));
			sv_utf8_decode(sv);
		}
	}
	
	return av;
}

void dbd_st_destroy(SV *sth, imp_sth_t *imp_sth)
{
	try {
		if (imp_sth->rs)
			imp_sth->rs->close();

		imp_sth->pstmt->close();
	} catch (NuoDB::SQLException& xcp) {
		do_error(sth, xcp.getSqlcode(), (char *) xcp.getText());
	}

	DBIc_IMPSET_off(imp_sth);
}

int dbd_st_finish(SV* sth, imp_sth_t* imp_sth)
{
	return TRUE;
}

int dbd_db_commit(SV* dbh, imp_dbh_t* imp_dbh)
{
	try {
		imp_dbh->conn->commit();
	} catch (NuoDB::SQLException& xcp) {
		do_error(dbh, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}

	return TRUE;
}

int dbd_db_rollback(SV* dbh, imp_dbh_t* imp_dbh)
{
	try {
		imp_dbh->conn->rollback();
	} catch (NuoDB::SQLException& xcp) {
		do_error(dbh, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}

	return TRUE;
}

SV* dbd_db_FETCH_attrib(SV *dbh, imp_dbh_t *imp_dbh, SV *keysv)
{
        STRLEN kl;
        char *key = SvPV(keysv, kl);

        if (kl==10 && strEQ(key, "AutoCommit")) {
		return sv_2mortal(boolSV(DBIc_has(imp_dbh, DBIcf_AutoCommit)));
	} else {
		return Nullsv;
	}
}

int dbd_db_STORE_attrib(SV* dbh, imp_dbh_t* imp_dbh, SV* keysv, SV* valuesv)
{
	STRLEN kl;
	char *key = SvPV(keysv, kl);
	bool bool_value = SvTRUE(valuesv);

	if (kl==10 && strEQ(key, "AutoCommit")) {
		try {
			imp_dbh->conn->setAutoCommit(bool_value);
			DBIc_set(imp_dbh, DBIcf_AutoCommit, bool_value);
		} catch (NuoDB::SQLException& xcp) {
			do_error(dbh, xcp.getSqlcode(), (char *) xcp.getText());
			return FALSE;
		}
	} else {
		return FALSE;
	}

	return TRUE;
}

SV* dbd_st_FETCH_attrib(SV *sth, imp_sth_t *imp_sth, SV *keysv)
{
	return Nullsv;
}

int dbd_st_STORE_attrib(SV *sth, imp_sth_t *imp_sth, SV *keysv, SV *valuesv)
{
	return FALSE;
}


int dbd_st_blob_read (SV *sth, imp_sth_t *imp_sth, int field, long offset, long len, SV *destrv, long destoffset)
{
	return FALSE;
}

int dbd_db_disconnect(SV* dbh, imp_dbh_t* imp_dbh)
{
	if (!imp_dbh->conn)
		return FALSE;

	try {
		imp_dbh->conn->close();
	} catch (NuoDB::SQLException& xcp) {
		do_error(dbh, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}

	return TRUE;
}

int dbd_bind_ph (SV *sth, imp_sth_t *imp_sth, SV *param, SV *value, IV sql_type, SV *attribs, int is_inout, IV maxlen)
{
	STRLEN value_len;

	if (is_inout)
		croak("Can't bind ``lvalue'' mode.");

	if (!imp_sth)
		return FALSE;

	sv_utf8_decode(value);

	char * value_str = SvPV(value, value_len);

	try {
		if (memchr(value_str, 0, value_len)) {
//			NuoDB::Blob* blob = imp_dbh->conn->createBlob();
//			blob->setBytes(value_len, (const unsigned char*) value_str);
//			imp_sth->pstmt->setBlob(SvIV(param), blob);
//			blob->release();

			imp_sth->pstmt->setBytes(SvIV(param), value_len, value_str);
		} else {
			imp_sth->pstmt->setString(SvIV(param), value_str);
		}
	} catch (NuoDB::SQLException& xcp) {
		do_error(sth, xcp.getSqlcode(), (char *) xcp.getText());
		return FALSE;
	}

	return TRUE;
}

void dbd_db_destroy(SV* dbh, imp_dbh_t* imp_dbh)
{
	imp_dbh->conn = NULL;
	DBIc_IMPSET_off(imp_dbh);
}

void do_error(SV* h, int rc, char* what)
{
        D_imp_xxh(h);

        sv_setiv(DBIc_ERR(imp_xxh), (IV)rc);

        SV *errstr = DBIc_ERRSTR(imp_xxh);

	SvUTF8_on(errstr);
        sv_setpv(errstr, what);
	sv_utf8_decode(errstr);

}
