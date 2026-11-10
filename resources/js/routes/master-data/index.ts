import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import districts from './districts'
import staffMembers from './staff-members'
import studyPrograms from './study-programs'
import faculties from './faculties'
import letterTemplates from './letter-templates'
import pmbSignatory from './pmb-signatory'
import letterhead from './letterhead'
/**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/master-data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MasterDataController::__invoke
 * @see app/Http/Controllers/MasterDataController.php:16
 * @route '/master-data'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
export const schools = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: schools.url(options),
    method: 'get',
})

schools.definition = {
    methods: ["get","head"],
    url: '/master-data/schools',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
schools.url = (options?: RouteQueryOptions) => {
    return schools.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
schools.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: schools.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
schools.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: schools.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
    const schoolsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: schools.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
        schoolsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: schools.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\InstitutionController::schools
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/schools'
 */
        schoolsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: schools.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    schools.form = schoolsForm
/**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
export const pesantren = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pesantren.url(options),
    method: 'get',
})

pesantren.definition = {
    methods: ["get","head"],
    url: '/master-data/pesantren',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
pesantren.url = (options?: RouteQueryOptions) => {
    return pesantren.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
pesantren.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pesantren.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
pesantren.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pesantren.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
    const pesantrenForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pesantren.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
        pesantrenForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pesantren.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\InstitutionController::pesantren
 * @see app/Http/Controllers/InstitutionController.php:20
 * @route '/master-data/pesantren'
 */
        pesantrenForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pesantren.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    pesantren.form = pesantrenForm
const masterData = {
    index: Object.assign(index, index),
schools: Object.assign(schools, schools),
pesantren: Object.assign(pesantren, pesantren),
districts: Object.assign(districts, districts),
staffMembers: Object.assign(staffMembers, staffMembers),
studyPrograms: Object.assign(studyPrograms, studyPrograms),
faculties: Object.assign(faculties, faculties),
letterTemplates: Object.assign(letterTemplates, letterTemplates),
pmbSignatory: Object.assign(pmbSignatory, pmbSignatory),
letterhead: Object.assign(letterhead, letterhead),
}

export default masterData
